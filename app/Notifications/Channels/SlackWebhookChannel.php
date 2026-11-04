<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/** Posts to the Slack "incoming webhook" URL saved in the user's alert rules. */
class SlackWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $notifiable->alertRule->slack_webhook_url;

        if ($url && method_exists($notification, 'toSlackWebhook')) {
            Http::timeout(10)->post($url, $notification->toSlackWebhook($notifiable))->throw();
        }
    }
}
