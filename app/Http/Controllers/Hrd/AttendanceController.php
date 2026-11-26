<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\HsmAttendanceBridgeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request, HsmAttendanceBridgeService $bridge)
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();

        $day = $bridge->forDate($date);
        $attendances = $day['present']->values();
        $nonWorking = $day['non_working']->values();

        $accountedIds = $attendances->pluck('user_id')
            ->merge($nonWorking->pluck('user_id'))
            ->unique()
            ->values();

        $absent = User::freelancers()
            ->active()
            ->whereNotIn('id', $accountedIds)
            ->orderBy('name')
            ->get();

        return view('hrd.attendances.index', [
            'date' => $date,
            'attendances' => $attendances,
            'nonWorking' => $nonWorking,
            'absent' => $absent,
            'portalUrl' => $bridge->portalUrl(),
            'stats' => [
                'present' => $attendances->count(),
                'on_time' => $attendances->where('status', Attendance::STATUS_ON_TIME)->count(),
                'late' => $attendances->where('status', Attendance::STATUS_LATE)->count(),
                'invalid' => $attendances->where('status', Attendance::STATUS_INVALID)->count(),
                'checked_out' => $attendances->filter(fn (Attendance $a) => $a->check_out_at !== null)->count(),
                'non_working' => $nonWorking->count(),
                'absent' => $absent->count(),
            ],
        ]);
    }

    public function store(Request $request, HsmAttendanceBridgeService $bridge): RedirectResponse
    {
        return redirect()
            ->route('hrd.attendances.index', ['date' => $request->input('date', today()->toDateString())])
            ->with('error', 'Pencatatan dan validasi presensi sepenuhnya dikelola melalui aplikasi absensi utama HSM ('.$bridge->portalUrl().').');
    }
}
