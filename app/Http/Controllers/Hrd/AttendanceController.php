<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();

        $attendances = Attendance::with(['user:id,name,nik', 'outlet:id,name'])
            ->whereDate('date', $date)->orderBy('check_in_at')->get();

        $absent = User::freelancers()->active()->whereNotIn('id', $attendances->pluck('user_id'))->orderBy('name')->get();

        return view('hrd.attendances.index', [
            'date' => $date,
            'attendances' => $attendances,
            'absent' => $absent,
        ]);
    }

    public function store(Request $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['required', 'string', 'min:5', 'max:200'],
        ], ['notes.required' => 'Alasan input manual wajib diisi.']);

        $freelancer = User::freelancers()->findOrFail($data['user_id']);
        $service->manualEntry($freelancer, Carbon::parse($data['date']), $data['check_in_time'], $data['check_out_time'] ?? null, $data['notes'], $request->user());

        return redirect()->route('hrd.attendances.index', ['date' => $data['date']])->with('success', 'Presensi manual berhasil dicatat.');
    }
}
