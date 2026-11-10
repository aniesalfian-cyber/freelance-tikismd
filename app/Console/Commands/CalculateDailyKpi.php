<?php

namespace App\Console\Commands;

use App\Services\PerformanceScoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateDailyKpi extends Command
{
    protected $signature = 'kpi:calculate-daily {--date= : Tanggal (Y-m-d), default hari ini}';
    protected $description = 'Hitung skor KPI harian seluruh freelancer';

    public function handle(PerformanceScoringService $scoring): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $count = $scoring->calculateForDate($date);
        $this->info("KPI {$date->toDateString()}: {$count} freelancer dinilai.");

        return self::SUCCESS;
    }
}
