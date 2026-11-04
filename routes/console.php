<?php

use App\Models\Monitor;
use App\Support\AlertManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('monitors:check', function () {
    $due = Monitor::where('is_active', true)->get()->filter->isDue();

    foreach ($due as $monitor) {
        $check = $monitor->runCheck();

        $this->line(sprintf(
            '%s %s %s (%sms)',
            $check->is_up ? 'UP  ' : 'DOWN',
            $monitor->name,
            $check->status_code ?? $check->error,
            $check->response_ms,
        ));
    }

    $this->info($due->count().' monitor(s) checked.');
})->purpose('Check every monitor whose interval has passed');

Artisan::command('alerts:evaluate', function () {
    AlertManager::checkErrorRates();
    $this->info('Error rates evaluated.');
})->purpose('Open or resolve error-rate alerts for every application');

Schedule::command('monitors:check')->everyThirtySeconds()->withoutOverlapping();
Schedule::command('alerts:evaluate')->everyMinute()->withoutOverlapping();
