<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Outlet;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function show(Request $request, AttendanceService $service)
    {
        return view('pwa.attendance', [
            'office' => Outlet::mainOffice(),
            'today' => $service->today($request->user()),
            'history' => Attendance::where('user_id', $request->user()->id)->latest('date')->limit(10)->get(),
        ]);
    }

    public function checkIn(Request $request, AttendanceService $service): JsonResponse
    {
        $data = $this->coords($request);
        $attendance = $service->checkIn($request->user(), $data['latitude'], $data['longitude'], $data['accuracy']);

        return response()->json([
            'ok' => true,
            'message' => $attendance->status === Attendance::STATUS_ON_TIME
                ? 'Presensi masuk berhasil dikonfirmasi. Tepat waktu!'
                : "Presensi masuk tercatat. Anda terlambat {$attendance->late_minutes} menit.",
            'status' => $attendance->status,
        ]);
    }

    public function checkOut(Request $request, AttendanceService $service): JsonResponse
    {
        $data = $this->coords($request);
        $service->checkOut($request->user(), $data['latitude'], $data['longitude'], $data['accuracy']);

        return response()->json(['ok' => true, 'message' => 'Presensi pulang berhasil dicatat. Terima kasih!']);
    }

    protected function coords(Request $request): array
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        return [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        ];
    }
}
