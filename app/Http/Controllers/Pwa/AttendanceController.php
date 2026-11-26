<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function show(Request $request, AttendanceService $service)
    {
        $user = $request->user();
        $today = $service->today($user);

        return view('pwa.attendance', [
            'office' => Outlet::mainOffice(),
            'today' => $today,
            'todayStatus' => $today ?? $service->todayStatus($user),
            'history' => $service->history($user, 15),
            'portalUrl' => $service->portalUrl(),
        ]);
    }

    public function checkIn(Request $request, AttendanceService $service): JsonResponse
    {
        $service->checkIn($request->user(), 0.0, 0.0, null);

        return response()->json(['ok' => false], 422);
    }

    public function checkOut(Request $request, AttendanceService $service): JsonResponse
    {
        $service->checkOut($request->user(), 0.0, 0.0, null);

        return response()->json(['ok' => false], 422);
    }
}
