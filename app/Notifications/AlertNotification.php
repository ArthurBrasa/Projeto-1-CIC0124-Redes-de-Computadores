<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\Channels\SlackWebhookChannel;
use App\Notifications\Channels\WebhookChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an alert opens or resolves, through the channels the user enabled
 * in OPTIONS > ALERT RULES. Queued, so checks and webhooks are never slowed down.
 */
class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param 'opened'|'resolved' $event */
    public function __construct(public Alert $alert, public string $event) {}

    public function via(User $notifiable): array
    {
        $rules = $notifiable->alertRule;
        $channels = [];

        if ($rules->notify_email) {
            $channels[] = 'mail';
        }
        if ($rules->notify_slack && $rules->slack_webhook_url) {
            $channels[] = SlackWebhookChannel::class;
        }
        if ($rules->notify_webhook && $rules->webhook_url) {
            $channels[] = WebhookChannel::class;
        }

        return $channels;
    }

    private function headline(): string
    {
        return $this->event === 'opened'
            ? '['.$this->alert->typeLabel().'] '.$this->alert->title
            : '[RESOLVED] '.$this->alert->title;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('PULSEMONITOR '.$this->headline())
            ->greeting($this->event === 'opened' ? 'Something needs your attention' : 'Back to normal');

        if ($this->event === 'opened') {
            $mail->error()
                ->line($this->alert->summary())
                ->line('Started at '.$this->alert->started_at->local()->format('M d, H:i').'.');
        } else {
            $mail->success()
                ->line($this->alert->title.' was resolved after '.$this->alert->duration().'.');
        }

        return $mail->action('Open alerts', route('alerts.index'));
    }

    /** Slack incoming webhook message. */
    public function toSlackWebhook(User $notifiable): array
    {
        $icon = $this->event === 'opened' ? ':rotating_light:' : ':white_check_mark:';
        $text = $this->event === 'opened'
            ? $this->alert->summary()
            : 'Resolved after '.$this->alert->duration();

        return ['text' => "{$icon} *{$this->headline()}*\n{$text}\n<".route('alerts.index').'|Open alerts>'];
    }

    /** Generic JSON webhook body. */
    public function toWebhook(User $notifiable): array
    {
        return [
            'event' => 'alert.'.$this->event,
            'alert' => [
                'id' => $this->alert->id,
                'type' => $this->alert->type,
                'severity' => $this->alert->severity(),
                'status' => $this->alert->status,
                'title' => $this->alert->title,
                'summary' => $this->alert->summary(),
                'details' => $this->alert->details,
                'started_at' => $this->alert->started_at->toIso8601String(),
                'resolved_at' => $this->alert->resolved_at?->toIso8601String(),
                'url' => $this->alert->subjectUrl(),
            ],
        ];
    }
}
