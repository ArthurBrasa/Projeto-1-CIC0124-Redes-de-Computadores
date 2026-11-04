<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/** Posts a JSON body to the webhook URL saved in the user's alert rules. */
class WebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $notifiable->alertRule->webhook_url;

        if ($url && method_exists($notification, 'toWebhook')) {
            Http::timeout(10)
                ->withHeaders(['User-Agent' => 'PULSEMONITOR'])
                ->post($url, $notification->toWebhook($notifiable))
                ->throw();
        }
    }
}
