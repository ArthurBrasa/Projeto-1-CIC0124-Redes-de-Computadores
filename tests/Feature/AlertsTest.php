<?php

use App\Models\Alert;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Support\AlertManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('opens one alert after 2 failed checks and resolves it when the monitor recovers', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->alertRule()->create(['notify_email' => true]);
    $monitor = $user->monitors()->create(['name' => 'api', 'url' => 'http://api.test/up']);

    Http::fake(['*' => Http::sequence()->push('down', 500)->push('down', 500)->push('down', 500)->push('ok', 200)]);

    $monitor->runCheck();
    expect($monitor->alerts()->count())->toBe(0);

    $monitor->runCheck();
    $monitor->runCheck();
    expect($monitor->alerts()->count())->toBe(1)->and($monitor->alerts()->first()->status)->toBe(Alert::ACTIVE);

    $monitor->update(['expected_status' => 200]);
    $monitor->runCheck();
    expect($monitor->alerts()->first()->status)->toBe(Alert::RESOLVED);

    Notification::assertSentTo($user, AlertNotification::class, 2);
});

it('opens an error rate alert only above the limit and with enough volume', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->alertRule()->create(['error_rate_limit' => 10]);
    $app = $user->applications()->create(['name' => 'a', 'environment' => 'development', 'url' => 'http://a.test']);

    foreach (range(1, 5) as $i) {
        $app->events()->create(['level' => 'error', 'message' => 'x']);
    }
    AlertManager::checkErrorRates();
    expect($app->alerts()->count())->toBe(0); // 100% errors, but below the minimum volume

    foreach (range(1, 20) as $i) {
        $app->events()->create(['level' => 'info', 'message' => 'x']);
    }
    AlertManager::checkErrorRates();
    expect($app->alerts()->open()->count())->toBe(1); // 5 of 25 = 20% > 10%
});
