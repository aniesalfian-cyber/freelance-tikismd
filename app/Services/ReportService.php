<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\PerformanceLog;
use App\Models\Setting;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public const TYPES = [
        'performa' => 'Laporan Evaluasi Performa Karyawan',
        'tugas' => 'Laporan Rincian Penugasan & Pengerjaan Tugas',
        'presensi' => 'Laporan Rekapitulasi Presensi Karyawan',
    ];

    /**
     * Susun laporan generik: dipakai oleh pratinjau HTML, PDF, dan Excel agar data selalu identik.
     *
     * @param  array{from?:string,to?:string,outlet_id?:int|string|null,user_id?:int|string|null,grade?:string|null}  $filters
     */
    public function build(string $type, array $filters): array
    {
        $type = array_key_exists($type, self::TYPES) ? $type : 'performa';
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now())->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'outlet_id' => $filters['outlet_id'] ?? null,
            'user_id' => $filters['user_id'] ?? null,
            'grade' => $filters['grade'] ?? null,
        ];

        $data = match ($type) {
            'tugas' => $this->tasksReport($filters),
            'presensi' => $this->attendanceReport($filters),
            default => $this->performanceReport($filters),
        };

        return array_merge([
            'type' => $type,
            'title' => self::TYPES[$type],
            'period_label' => $from->translatedFormat('d F Y').' s/d '.$to->translatedFormat('d F Y'),
            'filters' => $filters,
            'filters_label' => $this->filtersLabel($filters),
            'company' => [
                'name' => Setting::get('company_name'),
                'address' => Setting::get('company_address'),
                'contact' => Setting::get('company_contact'),
            ],
            'document_number' => $this->documentNumber(),
            'printed_at' => now()->translatedFormat('d F Y H:i').' WIB',
        ], $data);
    }

    public function documentNumber(): string
    {
        $count = ActivityLog::where('action', 'report.export')
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();

        return sprintf('DOC/HSM-FL/REP/%s/%04d', now()->format('Ym'), $count + 1);
    }

    // ------------------------------------------------------------------ performa

    protected function performanceReport(array $f): array
    {
        $tasks = TaskAssignment::query()->notCancelled()
            ->whereBetween('assigned_date', [$f['from'], $f['to']])
            ->when($f['outlet_id'], fn ($q) => $q->where('outlet_id', $f['outlet_id']))
            ->when($f['user_id'], fn ($q) => $q->where('user_id', $f['user_id']))
            ->get()->groupBy('user_id');

        $logs = PerformanceLog::query()
            ->whereBetween('evaluation_date', [$f['from'], $f['to']])
            ->when($f['user_id'], fn ($q) => $q->where('user_id', $f['user_id']))
            ->get()->groupBy('user_id');

        $attendances = Attendance::query()->whereBetween('date', [$f['from'], $f['to']])->get()->groupBy('user_id');

        $users = User::freelancers()
            ->when($f['user_id'], fn ($q) => $q->where('id', $f['user_id']))
            ->orderBy('name')->get()
            ->filter(fn (User $u) => $tasks->has($u->id) || (! $f['outlet_id'] && $logs->has($u->id)));

        $rows = $users->map(function (User $u) use ($tasks, $logs, $attendances) {
            $t = $tasks->get($u->id, collect());
            $done = $t->where('status', TaskAssignment::STATUS_COMPLETED);
            $onTime = $done->filter(fn ($x) => (int) $x->deviationMinutes() <= 0)->count();
            $locOk = $t->where('is_location_valid', true)->count();
            $avgKpi = round((float) $logs->get($u->id, collect())->avg('final_kpi_score'), 2);
            $att = $attendances->get($u->id, collect());

            return [
                'name' => $u->name,
                'nik' => $u->nik ?: '-',
                'days' => $logs->get($u->id, collect())->count(),
                'tasks' => $done->count().' / '.$t->count(),
                'avg_duration' => $done->count() ? round($done->avg('actual_duration_minutes')).' mnt' : '-',
                'on_time' => $done->count() ? round($onTime / $done->count() * 100, 1).'%' : '-',
                'location' => $t->count() ? round($locOk / $t->count() * 100, 1).'%' : '-',
                'present' => $att->count().' hari',
                'kpi' => number_format($avgKpi, 2),
                'grade' => PerformanceScoringService::gradeFor($avgKpi),
                '_raw' => [
                    'total' => $t->count(), 'done' => $done->count(), 'on_time' => $onTime,
                    'loc_bad' => $done->where('is_location_valid', false)->count(), 'kpi' => $avgKpi,
                ],
            ];
        })->values();

        if ($f['grade']) {
            $rows = $rows->where('grade', $f['grade'])->values();
        }

        $rows = $rows->sortByDesc(fn ($r) => (float) $r['kpi'])->values()
            ->map(function ($r, $i) {
                $r['no'] = $i + 1;

                return $r;
            });

        $totalTasks = $rows->sum(fn ($r) => $r['_raw']['total']);
        $doneTasks = $rows->sum(fn ($r) => $r['_raw']['done']);
        $onTime = $rows->sum(fn ($r) => $r['_raw']['on_time']);
        $locBad = $rows->sum(fn ($r) => $r['_raw']['loc_bad']);
        $avgKpi = $rows->count() ? round($rows->avg(fn ($r) => $r['_raw']['kpi']), 2) : 0;

        $notes = [];
        if ($rows->isNotEmpty()) {
            $best = $rows->first();
            $notes[] = "Performa tertinggi diraih {$best['name']} dengan skor KPI {$best['kpi']} (Grade {$best['grade']}).";
            $low = $rows->where('grade', 'D');
            $notes[] = $low->isEmpty()
                ? 'Tidak terdapat karyawan pada kategori Grade D (perlu evaluasi) pada periode ini.'
                : $low->count().' karyawan berada pada Grade D dan direkomendasikan untuk pembinaan: '.$low->pluck('name')->implode(', ').'.';
            $notes[] = $locBad > 0
                ? "Terdeteksi {$locBad} penyelesaian tugas di luar radius gerai; mohon dilakukan verifikasi lapangan."
                : 'Seluruh tugas selesai terverifikasi berada dalam radius gerai yang ditetapkan.';
            if ($doneTasks) {
                $notes[] = 'Tingkat ketepatan SLA tim sebesar '.round($onTime / $doneTasks * 100, 1).'% dari '.$doneTasks.' tugas yang diselesaikan.';
            }
        } else {
            $notes[] = 'Tidak ada data performa pada periode dan filter yang dipilih.';
        }

        return [
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'center', 'width' => '4%'],
                ['key' => 'name', 'label' => 'Nama Karyawan', 'align' => 'left'],
                ['key' => 'nik', 'label' => 'NIK', 'align' => 'left'],
                ['key' => 'days', 'label' => 'Hari Dinilai', 'align' => 'center'],
                ['key' => 'tasks', 'label' => 'Tugas (Selesai/Total)', 'align' => 'center'],
                ['key' => 'avg_duration', 'label' => 'Rata-rata Durasi', 'align' => 'center'],
                ['key' => 'on_time', 'label' => 'Tepat SLA', 'align' => 'center'],
                ['key' => 'location', 'label' => 'Akurasi Lokasi', 'align' => 'center'],
                ['key' => 'present', 'label' => 'Presensi', 'align' => 'center'],
                ['key' => 'kpi', 'label' => 'Skor KPI', 'align' => 'center'],
                ['key' => 'grade', 'label' => 'Grade', 'align' => 'center', 'badge' => 'grade'],
            ],
            'rows' => $rows->map(fn ($r) => collect($r)->except('_raw')->all())->all(),
            'summary' => [
                ['label' => 'Karyawan Dievaluasi', 'value' => $rows->count().' orang'],
                ['label' => 'Total Tugas', 'value' => (string) $totalTasks],
                ['label' => 'Tugas Selesai', 'value' => (string) $doneTasks],
                ['label' => 'Tingkat Tepat SLA', 'value' => $doneTasks ? round($onTime / $doneTasks * 100, 1).'%' : '-'],
                ['label' => 'Rata-rata Skor KPI', 'value' => number_format($avgKpi, 2).' ('.PerformanceScoringService::gradeFor($avgKpi).')'],
                ['label' => 'Pelanggaran Lokasi', 'value' => $locBad.' kasus'],
            ],
            'notes' => $notes,
        ];
    }

    // --------------------------------------------------------------------- tugas

    protected function tasksReport(array $f): array
    {
        $tasks = TaskAssignment::query()->with(['user:id,name', 'outlet:id,name'])
            ->whereBetween('assigned_date', [$f['from'], $f['to']])
            ->when($f['outlet_id'], fn ($q) => $q->where('outlet_id', $f['outlet_id']))
            ->when($f['user_id'], fn ($q) => $q->where('user_id', $f['user_id']))
            ->orderBy('assigned_date')->orderBy('scheduled_start_time')->get();

        $rows = $tasks->values()->map(fn (TaskAssignment $t, $i) => [
            'no' => $i + 1,
            'number' => $t->task_number,
            'date' => $t->assigned_date->format('d/m/Y'),
            'user' => $t->user?->name,
            'outlet' => $t->outlet?->name,
            'title' => $t->title,
            'type' => $t->task_type === TaskAssignment::TYPE_FIXED ? 'Pasti' : 'Tidak Pasti',
            'started' => $t->started_at?->format('H:i') ?? '-',
            'finished' => $t->completed_at?->format('H:i') ?? '-',
            'duration' => $t->actual_duration_minutes !== null ? $t->actual_duration_minutes.' / '.$t->estimated_duration_minutes.' mnt' : '- / '.$t->estimated_duration_minutes.' mnt',
            'location' => $t->is_location_valid === null ? '-' : ($t->is_location_valid ? 'Valid' : 'Tidak Valid'),
            'status' => $t->statusLabel(),
        ]);

        $completed = $tasks->where('status', TaskAssignment::STATUS_COMPLETED);
        $onTime = $completed->filter(fn ($t) => (int) $t->deviationMinutes() <= 0)->count();
        $locBad = $completed->where('is_location_valid', false)->count();

        $notes = [];
        if ($tasks->isEmpty()) {
            $notes[] = 'Tidak ada data penugasan pada periode dan filter yang dipilih.';
        } else {
            $notes[] = 'Rasio penyelesaian '.round($completed->count() / max(1, $tasks->where('status', '!=', 'cancelled')->count()) * 100, 1).'% dari seluruh tugas aktif.';
            $notes[] = $locBad ? "{$locBad} tugas selesai di luar radius gerai dan perlu ditinjau." : 'Seluruh tugas selesai berada dalam radius gerai.';
        }

        return [
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'center', 'width' => '3%'],
                ['key' => 'number', 'label' => 'No. Tugas', 'align' => 'left'],
                ['key' => 'date', 'label' => 'Tanggal', 'align' => 'center'],
                ['key' => 'user', 'label' => 'Karyawan', 'align' => 'left'],
                ['key' => 'outlet', 'label' => 'Gerai', 'align' => 'left'],
                ['key' => 'title', 'label' => 'Tugas', 'align' => 'left'],
                ['key' => 'type', 'label' => 'Tipe', 'align' => 'center'],
                ['key' => 'started', 'label' => 'Mulai', 'align' => 'center'],
                ['key' => 'finished', 'label' => 'Selesai', 'align' => 'center'],
                ['key' => 'duration', 'label' => 'Durasi / SLA', 'align' => 'center'],
                ['key' => 'location', 'label' => 'Lokasi', 'align' => 'center'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'center'],
            ],
            'rows' => $rows->all(),
            'summary' => [
                ['label' => 'Total Penugasan', 'value' => (string) $tasks->count()],
                ['label' => 'Selesai', 'value' => (string) $completed->count()],
                ['label' => 'Sedang Diproses', 'value' => (string) $tasks->where('status', 'in_progress')->count()],
                ['label' => 'Belum Diproses', 'value' => (string) $tasks->where('status', 'pending')->count()],
                ['label' => 'Tepat SLA', 'value' => $completed->count() ? round($onTime / $completed->count() * 100, 1).'%' : '-'],
                ['label' => 'Pelanggaran Lokasi', 'value' => $locBad.' kasus'],
            ],
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------ presensi

    protected function attendanceReport(array $f): array
    {
        $items = Attendance::query()->with('user:id,name,nik')
            ->whereBetween('date', [$f['from'], $f['to']])
            ->when($f['user_id'], fn ($q) => $q->where('user_id', $f['user_id']))
            ->orderBy('date')->orderBy('check_in_at')->get();

        $rows = $items->values()->map(fn (Attendance $a, $i) => [
            'no' => $i + 1,
            'date' => $a->date->format('d/m/Y'),
            'user' => $a->user?->name,
            'nik' => $a->user?->nik ?: '-',
            'in' => $a->check_in_at->format('H:i'),
            'out' => $a->check_out_at?->format('H:i') ?? '-',
            'work' => $a->workMinutes() !== null ? floor($a->workMinutes() / 60).'j '.($a->workMinutes() % 60).'m' : '-',
            'distance' => $a->check_in_distance_meters.' m',
            'late' => $a->late_minutes > 0 ? $a->late_minutes.' mnt' : '-',
            'status' => $a->statusLabel(),
        ]);

        $late = $items->where('status', Attendance::STATUS_LATE);

        return [
            'columns' => [
                ['key' => 'no', 'label' => 'No', 'align' => 'center', 'width' => '4%'],
                ['key' => 'date', 'label' => 'Tanggal', 'align' => 'center'],
                ['key' => 'user', 'label' => 'Karyawan', 'align' => 'left'],
                ['key' => 'nik', 'label' => 'NIK', 'align' => 'left'],
                ['key' => 'in', 'label' => 'Masuk', 'align' => 'center'],
                ['key' => 'out', 'label' => 'Pulang', 'align' => 'center'],
                ['key' => 'work', 'label' => 'Durasi Kerja', 'align' => 'center'],
                ['key' => 'distance', 'label' => 'Jarak Check-in', 'align' => 'center'],
                ['key' => 'late', 'label' => 'Terlambat', 'align' => 'center'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'center'],
            ],
            'rows' => $rows->all(),
            'summary' => [
                ['label' => 'Total Presensi', 'value' => (string) $items->count()],
                ['label' => 'Hadir Tepat Waktu', 'value' => (string) $items->where('status', Attendance::STATUS_ON_TIME)->count()],
                ['label' => 'Terlambat', 'value' => (string) $late->count()],
                ['label' => 'Rata-rata Keterlambatan', 'value' => $late->count() ? round($late->avg('late_minutes')).' mnt' : '-'],
                ['label' => 'Karyawan Hadir', 'value' => $items->pluck('user_id')->unique()->count().' orang'],
                ['label' => 'Belum Presensi Pulang', 'value' => (string) $items->whereNull('check_out_at')->count()],
            ],
            'notes' => [
                $items->isEmpty()
                    ? 'Tidak ada data presensi pada periode dan filter yang dipilih.'
                    : ($late->count() ? $late->count().' presensi tercatat terlambat melewati jam mulai kerja '.Setting::get('work_start_time').'.' : 'Seluruh presensi tercatat tepat waktu.'),
            ],
        ];
    }

    protected function filtersLabel(array $f): string
    {
        $parts = [];
        if ($f['outlet_id']) {
            $parts[] = 'Gerai: '.(\App\Models\Outlet::find($f['outlet_id'])?->name ?? '-');
        }
        if ($f['user_id']) {
            $parts[] = 'Karyawan: '.(User::find($f['user_id'])?->name ?? '-');
        }
        if ($f['grade']) {
            $parts[] = 'Grade: '.$f['grade'];
        }

        return $parts ? implode(' | ', $parts) : 'Semua Gerai & Semua Karyawan';
    }
}
