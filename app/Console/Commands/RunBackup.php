<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessRuleException;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Backup database ke storage/app/backups (.sql.gz)';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create('auto_scheduled');
        } catch (BusinessRuleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup dibuat: {$backup->filename} ({$backup->humanSize()})");

        return self::SUCCESS;
    }
}
