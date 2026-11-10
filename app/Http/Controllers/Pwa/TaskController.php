<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Services\AttendanceService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $scope = in_array($request->get('scope'), ['today', 'upcoming', 'history'], true) ? $request->get('scope') : 'today';

        $tasks = TaskAssignment::with('outlet:id,name')->where('user_id', $request->user()->id)
            ->when($scope === 'today', fn ($q) => $q->whereDate('assigned_date', today())->orderBy('scheduled_start_time'))
            ->when($scope === 'upcoming', fn ($q) => $q->whereDate('assigned_date', '>', today())->orderBy('assigned_date')->orderBy('scheduled_start_time'))
            ->when($scope === 'history', fn ($q) => $q->whereDate('assigned_date', '<', today())->orderByDesc('assigned_date')->orderBy('scheduled_start_time'))
            ->paginate(15)->withQueryString();

        return view('pwa.tasks.index', ['tasks' => $tasks, 'scope' => $scope]);
    }

    public function show(Request $request, TaskAssignment $task, AttendanceService $attendance)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $task->load(['outlet', 'creator:id,name']);

        return view('pwa.tasks.show', [
            'task' => $task,
            'attended' => (bool) $attendance->today($request->user()),
        ]);
    }

    public function start(Request $request, TaskAssignment $task, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        $task = $tasks->start($task, $request->user(), (float) $data['latitude'], (float) $data['longitude'], isset($data['accuracy']) ? (float) $data['accuracy'] : null);
        $inRadius = $task->start_distance_meters <= $task->outlet->radius_meters;

        return response()->json([
            'ok' => true,
            'message' => $inRadius
                ? 'Tugas dimulai. Lokasi terverifikasi di dalam radius gerai.'
                : "Tugas dimulai, namun Anda berada {$task->start_distance_meters} m dari gerai (di luar radius {$task->outlet->radius_meters} m). Lokasi dicatat tidak valid.",
            'in_radius' => $inRadius,
            'redirect' => route('pwa.tasks.show', $task),
        ]);
    }

    public function complete(Request $request, TaskAssignment $task, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'min:10', 'max:2000'],
            'proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], ['notes.min' => 'Catatan pengerjaan minimal 10 karakter.', 'notes.required' => 'Catatan pengerjaan wajib diisi.']);

        $task = $tasks->complete(
            $task, $request->user(), (float) $data['latitude'], (float) $data['longitude'],
            isset($data['accuracy']) ? (float) $data['accuracy'] : null, $data['notes'], $request->file('proof')
        );

        $deviation = $task->deviationMinutes();

        return response()->json([
            'ok' => true,
            'message' => "Tugas selesai dalam {$task->actual_duration_minutes} menit (SLA {$task->estimated_duration_minutes} menit)".($deviation > 0 ? ", terlambat {$deviation} menit." : ', tepat waktu.'),
            'location_valid' => $task->is_location_valid,
            'redirect' => route('pwa.tasks.index'),
        ]);
    }
}
