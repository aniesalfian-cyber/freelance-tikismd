<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemBackup extends Model
{
    protected $fillable = [
        'filename', 'file_path', 'file_size_bytes', 'backup_type', 'status', 'error_message', 'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $i ? 2 : 0).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '0 B';
    }
}
