<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user:id,name,role')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->action.'%'))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', "%{$request->q}%"))
            ->latest()->paginate(25)->withQueryString();

        $actions = ['auth', 'attendance', 'task', 'user', 'password', 'backup', 'settings', 'report', 'kpi'];

        return view('admin.logs', ['logs' => $logs, 'actions' => $actions]);
    }
}
