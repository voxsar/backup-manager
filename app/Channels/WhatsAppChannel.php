<?php

namespace App\Channels;

use GuzzleHttp\Client;
use Illuminate\Notifications\Notification;
use App\Notifications\BackupStatusNotification;

/**
 * WhatsApp notification channel via CallMeBot API.
 *
 * Setup: the user must request an API key from CallMeBot by sending
 * "I allow callmebot to send me messages" to +34 644 68 79 97 on WhatsApp.
 * Config keys: phone (international format, no +), apikey
 */
class WhatsAppChannel
{
    public function __construct(private Client $http) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);
        $config  = $notifiable->channel_config ?? [];

        if (empty($config['phone']) || empty($config['apikey'])) {
            return;
        }

        $this->http->get(config('services.whatsapp.api_url', 'https://api.callmebot.com/whatsapp.php'), [
            'query' => [
                'phone'  => $config['phone'],
                'text'   => $message,
                'apikey' => $config['apikey'],
            ],
            'timeout' => 10,
            'http_errors' => false,
        ]);
    }
}
