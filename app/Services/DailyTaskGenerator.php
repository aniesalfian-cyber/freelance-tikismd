<?php

namespace App\Services;

use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyTaskGenerator
{
    /**
     * Generate tugas pasti harian dari template aktif untuk tanggal tertentu.
     * Idempoten: kombinasi (user, template, tanggal) tidak pernah ganda.
     *
     * @return int jumlah tugas baru yang dibuat
     */
    public function generate(Carbon|string $date, ?User $actor = null): int
    {
        $date = Carbon::parse($date)->startOfDay();
        $created = 0;
        $creatorId = $actor?->id ?? User::whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_HRD])->orderBy('id')->value('id');

        if (! $creatorId) {
            return 0;
        }

        $templates = TaskTemplate::query()
            ->where('is_active', true)
            ->where('task_type', TaskAssignment::TYPE_FIXED)
            ->whereNotNull('default_outlet_id')
            ->get()
            ->filter(fn (TaskTemplate $t) => $t->runsOnIsoDay($date->dayOfWeekIso));

        $activeFreelancers = User::freelancers()->active()->get();

        foreach ($templates as $template) {
            $assignees = $template->default_user_id
                ? $activeFreelancers->where('id', $template->default_user_id)
                : $activeFreelancers;

            foreach ($assignees as $user) {
                $exists = TaskAssignment::where('user_id', $user->id)
                    ->where('task_template_id', $template->id)
                    ->whereDate('assigned_date', $date)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::transaction(function () use ($template, $user, $date, $creatorId, &$created) {
                    TaskAssignment::create([
                        'task_number' => TaskAssignment::generateNumber($date),
                        'user_id' => $user->id,
                        'outlet_id' => $template->default_outlet_id,
                        'task_template_id' => $template->id,
                        'title' => $template->title,
                        'description' => $template->description,
                        'task_type' => TaskAssignment::TYPE_FIXED,
                        'assigned_date' => $date->toDateString(),
                        'scheduled_start_time' => $template->scheduled_start_time,
                        'estimated_duration_minutes' => $template->estimated_duration_minutes,
                        'status' => TaskAssignment::STATUS_PENDING,
                        'created_by' => $creatorId,
                    ]);
                    $created++;
                });
            }
        }

        return $created;
    }
}
