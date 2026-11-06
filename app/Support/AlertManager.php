<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Notifications\AlertNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Opens and resolves alerts. There is at most one open alert per (subject, type):
 * while the problem continues the same alert is kept and only its numbers change.
 */
class AlertManager
{
    /** Failed checks in a row before a monitor counts as down. */
    public const DOWN_AFTER = 2;

    /** Slow checks in a row before a latency alert opens. */
    public const SLOW_AFTER = 3;

    /** Window and minimum volume for the error rate. */
    public const ERROR_WINDOW_MINUTES = 15;

    public const ERROR_MIN_EVENTS = 20;

    /** Called after every monitor check. */
    public static function checkMonitor(Monitor $monitor, MonitorCheck $check): void
    {
        if (! $check->is_up) {
            $failed = $monitor->checks()->latest('checked_at')->limit(self::DOWN_AFTER)->get();

            if ($failed->count() === self::DOWN_AFTER && $failed->every(fn ($c) => ! $c->is_up)) {
                self::open($monitor, Alert::MONITOR_DOWN, $monitor->name.' is down', [
                    'error' => $check->error ?? 'HTTP '.$check->status_code,
                    'status_code' => $check->status_code,
                    'url' => $monitor->url,
                ]);
            }

            return;
        }

        self::resolve($monitor, Alert::MONITOR_DOWN);

        $limit = $monitor->user->alertRule->latency_limit_ms;

        if ($check->response_ms <= $limit) {
            self::resolve($monitor, Alert::LATENCY);

            return;
        }

        $slow = $monitor->checks()->latest('checked_at')->limit(self::SLOW_AFTER)->get();

        if ($slow->count() === self::SLOW_AFTER && $slow->every(fn ($c) => $c->is_up && $c->response_ms > $limit)) {
            self::open($monitor, Alert::LATENCY, $monitor->name.' is slow', [
                'response_ms' => $check->response_ms,
                'limit' => $limit,
                'url' => $monitor->url,
            ]);
        }
    }

    /** Called every minute by the scheduler (alerts:evaluate). */
    public static function checkErrorRates(): void
    {
        $stats = ApplicationEvent::query()
            ->where('created_at', '>=', now()->subMinutes(self::ERROR_WINDOW_MINUTES))
            ->selectRaw("application_id, count(*) as total, sum(case when level = 'error' then 1 else 0 end) as errors")
            ->groupBy('application_id')
            ->get()
            ->keyBy('application_id');

        // apps with recent events + apps with an open alert (so it can be resolved)
        $openIds = Alert::open()
            ->where('type', Alert::ERROR_RATE)
            ->where('alertable_type', (new Application)->getMorphClass())
            ->pluck('alertable_id');

        $apps = Application::with('user')->whereIn('id', $stats->keys()->merge($openIds)->unique())->get();

        foreach ($apps as $app) {
            $total = (int) ($stats[$app->id]->total ?? 0);
            $errors = (int) ($stats[$app->id]->errors ?? 0);
            $rate = $total ? round($errors / $total * 100, 2) : 0.0;
            $limit = (float) $app->user->alertRule->error_rate_limit;

            if ($total >= self::ERROR_MIN_EVENTS && $rate > $limit) {
                self::open($app, Alert::ERROR_RATE, $app->name.' error rate is high', [
                    'rate' => $rate,
                    'errors' => $errors,
                    'total' => $total,
                    'limit' => $limit,
                ]);
            } else {
                self::resolve($app, Alert::ERROR_RATE);
            }
        }
    }

    /** Open a new alert, or refresh the numbers of the one already open. */
    public static function open(Monitor|Application $subject, string $type, string $title, array $details): Alert
    {
        $alert = self::findOpen($subject, $type);

        if ($alert) {
            $alert->fill(['title' => $title, 'details' => $details]);

            if ($alert->isDirty()) {
                $alert->save();
            }

            return $alert;
        }

        $alert = $subject->alerts()->create([
            'user_id' => $subject->user_id,
            'type' => $type,
            'status' => Alert::ACTIVE,
            'title' => $title,
            'details' => $details,
            'started_at' => now(),
        ]);

        Log::channel('protocol')->notice('alert.opened', ['alert' => $alert->id, 'type' => $type, 'title' => $title, 'details' => $details]);
        $alert->user->notify(new AlertNotification($alert, 'opened'));

        return $alert;
    }

    public static function resolve(Monitor|Application $subject, string $type): void
    {
        $alert = self::findOpen($subject, $type);

        if (! $alert) {
            return;
        }

        $alert->update(['status' => Alert::RESOLVED, 'resolved_at' => now()]);
        Log::channel('protocol')->notice('alert.resolved', ['alert' => $alert->id, 'type' => $type, 'duration' => $alert->duration()]);

        $alert->user->notify(new AlertNotification($alert, 'resolved'));
    }

    private static function findOpen(Model $subject, string $type): ?Alert
    {
        return $subject->alerts()->open()->where('type', $type)->latest('id')->first();
    }
}
