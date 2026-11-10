<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\PerformanceLog;
use App\Models\TaskAssignment;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance)
    {
        $user = $request->user();
        $tasks = TaskAssignment::with('outlet:id,name')->where('user_id', $user->id)->whereDate('assigned_date', today())
            ->notCancelled()->orderBy('scheduled_start_time')->get();

        $done = $tasks->where('status', TaskAssignment::STATUS_COMPLETED)->count();
        $active = $tasks->firstWhere('status', TaskAssignment::STATUS_IN_PROGRESS);
        $next = $tasks->firstWhere('status', TaskAssignment::STATUS_PENDING);

        return view('pwa.workspace', [
            'attendance' => $attendance->today($user),
            'office' => Outlet::mainOffice(),
            'tasks' => $tasks,
            'done' => $done,
            'total' => $tasks->count(),
            'active' => $active,
            'next' => $next,
            'todayLog' => PerformanceLog::where('user_id', $user->id)->whereDate('evaluation_date', today())->first(),
            'monthAvg' => round((float) PerformanceLog::where('user_id', $user->id)->where('evaluation_date', '>=', today()->subDays(29))->avg('final_kpi_score'), 2),
        ]);
    }
}
