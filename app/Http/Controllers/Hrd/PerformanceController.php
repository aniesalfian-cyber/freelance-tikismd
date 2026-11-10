<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\PerformanceLog;
use App\Models\User;
use App\Services\PerformanceScoringService;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from) : today()->subDays(13);
        $to = $request->filled('to') ? Carbon::parse($request->to) : today();

        $query = PerformanceLog::with('user:id,name,nik')
            ->whereBetween('evaluation_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('grade'), fn ($q) => $q->where('grade', $request->grade));

        $avg = (float) (clone $query)->avg('final_kpi_score');

        return view('hrd.performance.index', [
            'logs' => $query->orderByDesc('evaluation_date')->orderByDesc('final_kpi_score')->paginate(20)->withQueryString(),
            'from' => $from,
            'to' => $to,
            'avg' => round($avg, 2),
            'avgGrade' => PerformanceScoringService::gradeFor($avg),
            'freelancers' => User::freelancers()->orderBy('name')->get(['id', 'name']),
            'weights' => app(PerformanceScoringService::class)->weights(),
        ]);
    }

    public function recalculate(Request $request, PerformanceScoringService $scoring): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);
        if ($from->diffInDays($to) > 92) {
            return back()->with('error', 'Rentang hitung ulang maksimal 92 hari.');
        }

        $total = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $total += $scoring->calculateForDate($d);
        }
        ActivityLogger::log('kpi.recalculated', "Hitung ulang KPI {$from->toDateString()} s/d {$to->toDateString()} ({$total} log)");

        return back()->with('success', "Penilaian KPI dihitung ulang: {$total} log diperbarui.");
    }
}
