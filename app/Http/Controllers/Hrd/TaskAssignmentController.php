<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\DailyTaskGenerator;
use App\Services\TaskService;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->date) : today();
        $range = $request->get('range') === 'all';

        $base = TaskAssignment::query()
            ->when(! $range, fn ($q) => $q->whereDate('assigned_date', $date))
            ->when($request->filled('division'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('specialization', $request->division)))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('outlet_id'), fn ($q) => $q->where('outlet_id', $request->outlet_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('task_type', $request->type))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$request->q}%")->orWhere('task_number', 'like', "%{$request->q}%")));

        $counts = (clone $base)->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status');

        $tasks = (clone $base)->with(['user:id,name,specialization', 'outlet:id,name'])
            ->orderByDesc('assigned_date')->orderBy('scheduled_start_time')->paginate(20)->withQueryString();

        return view('hrd.tasks.index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'date' => $date,
            'range' => $range,
            'freelancers' => User::freelancers()->orderBy('name')->get(['id', 'name', 'specialization']),
            'outlets' => Outlet::orderBy('name')->get(['id', 'name']),
            'divisions' => User::DIVISIONS,
        ]);
    }

    public function create()
    {
        return view('hrd.tasks.form', [
            'task' => new TaskAssignment(['task_type' => TaskAssignment::TYPE_ADHOC, 'assigned_date' => today(), 'scheduled_start_time' => now()->addMinutes(30)->format('H:i'), 'estimated_duration_minutes' => 30]),
            'freelancers' => User::freelancers()->active()->orderBy('name')->get(),
            'outlets' => Outlet::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
            'outlet_id' => ['required', 'exists:outlets,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'task_type' => ['required', 'in:'.TaskAssignment::TYPE_FIXED.','.TaskAssignment::TYPE_ADHOC],
            'assigned_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_start_time' => ['required', 'date_format:H:i'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5', 'max:720'],
        ], ['user_ids.required' => 'Pilih minimal satu karyawan.', 'assigned_date.after_or_equal' => 'Tanggal penugasan tidak boleh di masa lalu.']);

        $date = Carbon::parse($data['assigned_date']);
        $users = User::freelancers()->active()->whereIn('id', $data['user_ids'])->get();
        if ($users->isEmpty()) {
            return back()->withInput()->with('error', 'Karyawan yang dipilih tidak aktif / tidak valid.');
        }

        $created = DB::transaction(function () use ($data, $users, $request, $date) {
            return $users->map(fn (User $u) => TaskAssignment::create([
                'task_number' => TaskAssignment::generateNumber($date),
                'user_id' => $u->id,
                'outlet_id' => $data['outlet_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'task_type' => $data['task_type'],
                'assigned_date' => $date->toDateString(),
                'scheduled_start_time' => $data['scheduled_start_time'].':00',
                'estimated_duration_minutes' => $data['estimated_duration_minutes'],
                'status' => TaskAssignment::STATUS_PENDING,
                'created_by' => $request->user()->id,
            ]));
        });

        ActivityLogger::log('task.created', "Menugaskan '{$data['title']}' kepada {$created->count()} karyawan", $created->first());

        return redirect()->route('hrd.tasks.index', ['date' => $date->toDateString()])
            ->with('success', "{$created->count()} penugasan berhasil dibuat.");
    }

    public function downloadTemplate(\App\Services\TaskExcelService $excelService)
    {
        return $excelService->generateTemplate();
    }

    public function showImport()
    {
        return view('hrd.tasks.import');
    }

    public function import(Request $request, \App\Services\TaskExcelService $excelService): RedirectResponse
    {
        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'excel_file.required' => 'Pilih file Excel format (.xlsx / .csv) yang akan diimpor.',
            'excel_file.mimes' => 'Format file harus berupa Excel (.xlsx / .xls) atau .csv.',
            'excel_file.max' => 'Ukuran file maksimal adalah 10 MB.',
        ]);

        $result = $excelService->importTasks($request->file('excel_file'), $request->user()->id);

        if (! $result['success']) {
            return back()->with('error', 'Gagal mengimpor tugas: '.implode(' | ', array_slice($result['errors'], 0, 3)))
                ->with('import_errors', $result['errors']);
        }

        ActivityLogger::log('task.imported', "Mengimpor {$result['imported']} tugas kurir dari Excel");

        $msg = "{$result['imported']} penugasan kurir berhasil diimpor ke sistem.";
        if (! empty($result['errors'])) {
            $msg .= ' Namun '.count($result['errors']).' baris dilewati karena format tidak sesuai.';
        }

        return redirect()->route('hrd.tasks.index')
            ->with('success', $msg)
            ->with('import_errors', $result['errors']);
    }

    public function show(TaskAssignment $task)
    {
        $task->load(['user', 'outlet', 'creator:id,name', 'template:id,title']);

        return view('hrd.tasks.show', ['task' => $task]);
    }

    public function edit(TaskAssignment $task)
    {
        if ($task->status !== TaskAssignment::STATUS_PENDING) {
            return redirect()->route('hrd.tasks.show', $task)->with('error', 'Hanya tugas berstatus Belum Diproses yang dapat diubah.');
        }

        return view('hrd.tasks.form', [
            'task' => $task,
            'freelancers' => User::freelancers()->active()->orderBy('name')->get(),
            'outlets' => Outlet::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, TaskAssignment $task): RedirectResponse
    {
        if ($task->status !== TaskAssignment::STATUS_PENDING) {
            return redirect()->route('hrd.tasks.show', $task)->with('error', 'Hanya tugas berstatus Belum Diproses yang dapat diubah.');
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'outlet_id' => ['required', 'exists:outlets,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'task_type' => ['required', 'in:'.TaskAssignment::TYPE_FIXED.','.TaskAssignment::TYPE_ADHOC],
            'assigned_date' => ['required', 'date'],
            'scheduled_start_time' => ['required', 'date_format:H:i'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5', 'max:720'],
        ]);
        $data['scheduled_start_time'] .= ':00';

        $task->update($data);
        ActivityLogger::log('task.updated', "Memperbarui tugas {$task->task_number}", $task);

        return redirect()->route('hrd.tasks.show', $task)->with('success', 'Penugasan berhasil diperbarui.');
    }

    public function cancel(Request $request, TaskAssignment $task, TaskService $tasks): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);
        $tasks->cancel($task, $request->user(), $data['reason']);

        return back()->with('success', 'Tugas dibatalkan.');
    }

    public function destroy(TaskAssignment $task): RedirectResponse
    {
        if ($task->status !== TaskAssignment::STATUS_PENDING) {
            return back()->with('error', 'Hanya tugas berstatus Belum Diproses yang dapat dihapus. Gunakan pembatalan untuk tugas lain.');
        }
        $number = $task->task_number;
        $task->delete();
        ActivityLogger::log('task.deleted', "Menghapus tugas {$number}");

        return redirect()->route('hrd.tasks.index')->with('success', 'Penugasan dihapus.');
    }

    public function generate(Request $request, DailyTaskGenerator $generator): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date', 'after_or_equal:today']], ['date.after_or_equal' => 'Tanggal generate tidak boleh di masa lalu.']);
        $count = $generator->generate($data['date'], $request->user());
        ActivityLogger::log('task.generated', "Generate tugas harian {$data['date']}: {$count} tugas");

        return back()->with('success', $count ? "{$count} tugas harian baru berhasil digenerate." : 'Tidak ada tugas baru (semua template sudah tergenerate atau tidak ada template aktif).');
    }
}
