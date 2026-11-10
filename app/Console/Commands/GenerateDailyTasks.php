<?php

namespace App\Console\Commands;

use App\Services\DailyTaskGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyTasks extends Command
{
    protected $signature = 'tasks:generate-daily {--date= : Tanggal (Y-m-d), default hari ini}';
    protected $description = 'Generate tugas pasti harian dari template aktif untuk setiap freelancer';

    public function handle(DailyTaskGenerator $generator): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $count = $generator->generate($date);
        $this->info("Tugas harian {$date->toDateString()}: {$count} tugas baru dibuat.");

        return self::SUCCESS;
    }
}
