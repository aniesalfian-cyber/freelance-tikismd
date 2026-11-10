<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\PerformanceLog;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** Data dashboard eksekutif untuk rentang tanggal tertentu. */
    public function executive(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $tasks = TaskAssignment::query()->notCancelled()->whereBetween('assigned_date', [$from->toDateString(), $to->toDateString()]);
        $totalTasks = (clone $tasks)->count();
        $completedTasks = (clone $tasks)->where('status', TaskAssignment::STATUS_COMPLETED)->count();
        $totalMinutes = (int) (clone $tasks)->where('status', TaskAssignment::STATUS_COMPLETED)->sum('actual_duration_minutes');
        $onTime = (clone $tasks)->where('status', TaskAssignment::STATUS_COMPLETED)
            ->whereColumn('actual_duration_minutes', '<=', 'estimated_duration_minutes')->count();
        $locationViolations = (clone $tasks)->where('status', TaskAssignment::STATUS_COMPLETED)->where('is_location_valid', false)->count();

        $logs = PerformanceLog::query()->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()]);
        $avgKpi = (float) (clone $logs)->avg('final_kpi_score');

        $presentToday = Attendance::whereDate('date', today())->count();
        $freelancerTotal = User::freelancers()->active()->count();

        return [
            'cards' => [
                'freelancers_active' => $freelancerTotal,
                'present_today' => $presentToday,
                'completion_rate' => $totalTasks ? round($completedTasks / $totalTasks * 100, 1) : 0,
                'productive_hours' => round($totalMinutes / 60, 1),
                'avg_kpi' => round($avgKpi, 2),
                'avg_grade' => PerformanceScoringService::gradeFor($avgKpi),
                'on_time_rate' => $completedTasks ? round($onTime / $completedTasks * 100, 1) : 0,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'location_violations' => $locationViolations,
            ],
            'trend' => $this->trend($from, $to),
            'outlet_load' => $this->outletLoad($from, $to),
            'radar' => $this->radar($from, $to),
            'top' => $this->ranking($from, $to, 'desc'),
            'bottom' => $this->ranking($from, $to, 'asc'),
            'grade_distribution' => $this->gradeDistribution($from, $to),
            'map' => $this->liveOutletMap(),
        ];
    }

    public function trend(Carbon $from, Carbon $to): array
    {
        $kpi = PerformanceLog::query()
            ->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()])
            ->select('evaluation_date', DB::raw('AVG(final_kpi_score) as avg_kpi'))
            ->groupBy('evaluation_date')->pluck('avg_kpi', 'evaluation_date')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => (float) $v]);

        $done = TaskAssignment::query()->where('status', TaskAssignment::STATUS_COMPLETED)
            ->whereBetween('assigned_date', [$from->toDateString(), $to->toDateString()])
            ->select('assigned_date', DB::raw('COUNT(*) as c'))->groupBy('assigned_date')->pluck('c', 'assigned_date')
            ->mapWithKeys(fn ($v, $k) => [Carbon::parse($k)->toDateString() => (int) $v]);

        $labels = $kpiSeries = $doneSeries = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('d M');
            $kpiSeries[] = isset($kpi[$key]) ? round($kpi[$key], 2) : null;
            $doneSeries[] = $done[$key] ?? 0;
        }

        return ['labels' => $labels, 'kpi' => $kpiSeries, 'completed' => $doneSeries];
    }

    public function outletLoad(Carbon $from, Carbon $to): array
    {
        $rows = TaskAssignment::query()->notCancelled()
            ->join('outlets', 'outlets.id', '=', 'task_assignments.outlet_id')
            ->whereBetween('assigned_date', [$from->toDateString(), $to->toDateString()])
            ->select('outlets.name', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN task_assignments.status = 'completed' THEN 1 ELSE 0 END) as done"))
            ->groupBy('outlets.id', 'outlets.name')->orderByDesc('total')->limit(10)->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'total' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            'done' => $rows->pluck('done')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    public function radar(Carbon $from, Carbon $to): array
    {
        $row = PerformanceLog::query()
            ->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('AVG(completion_score) as c, AVG(time_score) as t, AVG(location_score) as l, AVG(attendance_score) as a')
            ->first();

        return [
            'labels' => ['Penyelesaian', 'Kecepatan (SLA)', 'Lokasi', 'Kehadiran'],
            'values' => [round((float) $row?->c, 1), round((float) $row?->t, 1), round((float) $row?->l, 1), round((float) $row?->a, 1)],
        ];
    }

    public function ranking(Carbon $from, Carbon $to, string $direction = 'desc', int $limit = 5)
    {
        return PerformanceLog::query()
            ->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()])
            ->select('user_id', DB::raw('AVG(final_kpi_score) as avg_kpi'), DB::raw('SUM(completed_tasks) as done'),
                DB::raw('SUM(total_tasks) as total'), DB::raw('COUNT(*) as days'))
            ->groupBy('user_id')
            ->orderBy('avg_kpi', $direction)
            ->limit($limit)->get()
            ->map(function ($r) {
                $r->user = User::find($r->user_id);
                $r->avg_kpi = round((float) $r->avg_kpi, 2);
                $r->grade = PerformanceScoringService::gradeFor($r->avg_kpi);

                return $r;
            })->filter(fn ($r) => $r->user);
    }

    public function gradeDistribution(Carbon $from, Carbon $to): array
    {
        $rows = PerformanceLog::query()
            ->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()])
            ->select('grade', DB::raw('COUNT(*) as c'))->groupBy('grade')->pluck('c', 'grade');

        return collect(['A', 'B', 'C', 'D'])->map(fn ($g) => (int) ($rows[$g] ?? 0))->all();
    }

    /**
     * Status gerai hari ini untuk peta live:
     *  merah  = ada keterlambatan SLA / anomali lokasi
     *  oranye = ada tugas sedang berjalan
     *  hijau  = normal
     */
    public function liveOutletMap(): array
    {
        $today = today()->toDateString();
        $tasks = TaskAssignment::query()->notCancelled()->with('user:id,name')
            ->whereDate('assigned_date', $today)->get()->groupBy('outlet_id');

        return Outlet::query()->active()->get()->map(function (Outlet $o) use ($tasks) {
            $list = $tasks->get($o->id, collect());
            $late = $list->filter(function (TaskAssignment $t) {
                return match ($t->status) {
                    TaskAssignment::STATUS_PENDING => $t->isLateToStart(),
                    TaskAssignment::STATUS_IN_PROGRESS => $t->slaDeadline() && now()->greaterThan($t->slaDeadline()),
                    TaskAssignment::STATUS_COMPLETED => $t->is_location_valid === false || (int) $t->deviationMinutes() > 15,
                    default => false,
                };
            })->count();
            $active = $list->where('status', TaskAssignment::STATUS_IN_PROGRESS);

            $status = $late > 0 ? 'danger' : ($active->isNotEmpty() ? 'orange' : 'success');

            return [
                'id' => $o->id,
                'name' => $o->name,
                'code' => $o->code,
                'lat' => $o->latitude,
                'lng' => $o->longitude,
                'radius' => $o->radius_meters,
                'main' => $o->is_main_office,
                'status' => $status,
                'tasks' => $list->count(),
                'done' => $list->where('status', TaskAssignment::STATUS_COMPLETED)->count(),
                'in_progress' => $active->map(fn ($t) => $t->user?->name)->filter()->unique()->values()->all(),
                'late' => $late,
            ];
        })->values()->all();
    }

    /** Ringkasan operasional hari ini (HRD). */
    public function today(): array
    {
        $base = TaskAssignment::query()->notCancelled()->whereDate('assigned_date', today());
        $freelancers = User::freelancers()->active()->get();
        $present = Attendance::whereDate('date', today())->get()->keyBy('user_id');

        return [
            'task_counts' => [
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'total' => (clone $base)->count(),
            ],
            'present' => $present->count(),
            'absent' => $freelancers->reject(fn ($u) => $present->has($u->id))->values(),
            'late_attendance' => $present->where('status', Attendance::STATUS_LATE)->count(),
            'late_tasks' => (clone $base)->where('status', 'pending')->whereTime('scheduled_start_time', '<', now()->format('H:i:s'))->count(),
            'freelancers_total' => $freelancers->count(),
        ];
    }
}
