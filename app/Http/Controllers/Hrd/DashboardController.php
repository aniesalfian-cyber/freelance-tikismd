<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        $today = $dashboard->today();

        return view('hrd.dashboard', [
            'today' => $today,
            'activeTasks' => TaskAssignment::with(['user:id,name', 'outlet:id,name'])
                ->whereDate('assigned_date', today())->where('status', TaskAssignment::STATUS_IN_PROGRESS)->latest('started_at')->get(),
            'lateTasks' => TaskAssignment::with(['user:id,name', 'outlet:id,name'])
                ->whereDate('assigned_date', today())->where('status', TaskAssignment::STATUS_PENDING)
                ->whereTime('scheduled_start_time', '<', now()->format('H:i:s'))->orderBy('scheduled_start_time')->limit(8)->get(),
            'map' => $dashboard->liveOutletMap(),
        ]);
    }
}
