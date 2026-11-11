<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

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
            'couriersMap' => $dashboard->liveCouriersMap(),
            'outlets' => Outlet::active()->orderByDesc('is_main_office')->orderBy('name')->get(['id', 'name', 'code', 'address', 'is_main_office']),
            'templates' => TaskTemplate::where('is_active', true)->orderBy('title')->get(['id', 'title', 'description', 'target_division', 'default_outlet_id', 'estimated_duration_minutes']),
        ]);
    }

    public function liveCouriers(DashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'updated_at' => now()->format('H:i:s'),
            'couriers' => $dashboard->liveCouriersMap(),
            'outlets' => $dashboard->liveOutletMap(),
        ]);
    }
}
