<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function index()
    {
        return view('hrd.templates.index', [
            'templates' => TaskTemplate::with(['outlet:id,name', 'user:id,name'])->orderByDesc('is_active')->orderBy('scheduled_start_time')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('hrd.templates.form', $this->formData(new TaskTemplate(['is_active' => true, 'scheduled_start_time' => '09:00', 'estimated_duration_minutes' => 30])));
    }

    public function store(Request $request): RedirectResponse
    {
        $template = TaskTemplate::create($this->validated($request));
        ActivityLogger::log('template.created', "Membuat template tugas {$template->title}", $template);

        return redirect()->route('hrd.templates.index')->with('success', 'Template tugas harian berhasil dibuat.');
    }

    public function edit(TaskTemplate $template)
    {
        return view('hrd.templates.form', $this->formData($template));
    }

    public function update(Request $request, TaskTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request));
        ActivityLogger::log('template.updated', "Memperbarui template {$template->title}", $template);

        return redirect()->route('hrd.templates.index')->with('success', 'Template tugas berhasil diperbarui.');
    }

    public function destroy(TaskTemplate $template): RedirectResponse
    {
        $title = $template->title;
        $template->delete(); // riwayat tugas tetap ada (task_template_id → NULL)
        ActivityLogger::log('template.deleted', "Menghapus template {$title}");

        return back()->with('success', 'Template dihapus. Tugas yang sudah digenerate tidak terpengaruh.');
    }

    protected function formData(TaskTemplate $template): array
    {
        return [
            'template' => $template,
            'outlets' => Outlet::active()->orderBy('name')->get(),
            'freelancers' => User::freelancers()->active()->orderBy('name')->get(),
            'divisions' => User::DIVISIONS,
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'target_division' => ['nullable', 'string', 'max:100'],
            'scheduled_start_time' => ['required', 'date_format:H:i'],
            'estimated_duration_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'default_outlet_id' => ['required', 'exists:outlets,id'],
            'default_user_id' => ['nullable', 'exists:users,id'],
            'active_days' => ['nullable', 'array'],
            'active_days.*' => ['integer', 'between:1,7'],
        ], ['default_outlet_id.required' => 'Pilih gerai lokasi pengerjaan.']);

        $days = collect($data['active_days'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
        $data['active_days'] = (count($days) === 0 || count($days) === 7) ? null : $days;
        $data['task_type'] = TaskAssignment::TYPE_FIXED;
        $data['is_active'] = $request->boolean('is_active');
        $data['default_user_id'] = $data['default_user_id'] ?? null;
        $data['target_division'] = $data['target_division'] ?: null;

        return $data;
    }
}
