<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class ActivityLogger
{
    public static function log(string $action, ?string $description = null, ?Model $subject = null, ?int $userId = null): void
    {
        try {
            $request = request();
            ActivityLog::create([
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'description' => $description ? Str::limit($description, 490, '') : null,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);
        } catch (Throwable $e) {
            // Pencatatan audit tidak boleh menggagalkan proses utama.
            report($e);
        }
    }
}
