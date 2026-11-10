<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\SystemBackup;
use App\Models\TaskAssignment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $roleCounts = User::query()->selectRaw('role, COUNT(*) as c, SUM(is_active) as active')->groupBy('role')->get()->keyBy('role');

        return view('admin.dashboard', [
            'roleCounts' => $roleCounts,
            'lastBackup' => SystemBackup::where('status', 'success')->latest()->first(),
            'backupCount' => SystemBackup::where('status', 'success')->count(),
            'resetRequests' => ActivityLog::where('action', 'password.reset_requested')->where('created_at', '>=', now()->subDays(3))->latest()->limit(5)->get(),
            'recentLogs' => ActivityLog::with('user:id,name')->latest()->limit(8)->get(),
            'stats' => [
                'outlets' => Outlet::active()->count(),
                'tasks_today' => TaskAssignment::notCancelled()->whereDate('assigned_date', today())->count(),
                'attendance_today' => Attendance::whereDate('date', today())->count(),
                'inactive_users' => User::where('is_active', false)->count(),
            ],
        ]);
    }
}
