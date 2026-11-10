<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExecutiveDashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $to = $request->filled('to') ? Carbon::parse($request->to) : today();
        $from = $request->filled('from') ? Carbon::parse($request->from) : $to->copy()->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 180) {
            $from = $to->copy()->subDays(180);
        }

        return view('owner.dashboard', [
            'from' => $from,
            'to' => $to,
            'data' => $dashboard->executive($from, $to),
        ]);
    }
}
