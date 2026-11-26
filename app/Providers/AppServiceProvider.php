<?php

namespace App\Providers;

use App\Services\HsmAttendanceBridgeService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(HsmAttendanceBridgeService::class);
    }

    public function boot(): void
    {
        //
    }
}
