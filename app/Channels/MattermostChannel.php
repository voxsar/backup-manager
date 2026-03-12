<?php

namespace App\Channels;

use GuzzleHttp\Client;
use Illuminate\Notifications\Notification;

/**
 * Mattermost notification channel via Incoming Webhooks.
 * Supports Slack-compatible message attachments for rich formatting.
 *
 * Config keys: webhook_url, username (optional), channel (optional)
 */
class MattermostChannel
{
    public function __construct(private Client $http) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toMattermost')) {
            return;
        }

        $message = $notification->toMattermost($notifiable);
        $config  = $notifiable->channel_config ?? [];

        if (empty($config['webhook_url'])) {
            return;
        }

        // Support both string (simple) and array (rich Slack-compatible) formats
        $payload = is_array($message) ? $message : ['text' => $message];

        if (! empty($config['username'])) {
            $payload['username'] = $config['username'];
        }

        if (! empty($config['channel'])) {
            $payload['channel'] = $config['channel'];
        }

        $this->http->post($config['webhook_url'], [
            'json'        => $payload,
            'timeout'     => 10,
            'http_errors' => false,
        ]);
    }
}
