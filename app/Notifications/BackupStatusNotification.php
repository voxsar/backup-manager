<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BackupStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $backupName,
        private string $status,
        private string $message = '',
    ) {}

    public function via(mixed $notifiable): array
    {
        return match ($notifiable->type ?? '') {
            'whatsapp'   => [\App\Channels\WhatsAppChannel::class],
            'mattermost' => [\App\Channels\MattermostChannel::class],
            default      => [],
        };
    }

    private function buildMessage(): string
    {
        $emoji = $this->status === 'success' ? '✅' : '❌';
        $text  = "{$emoji} *Backup {$this->status}* — {$this->backupName}";

        if ($this->message) {
            $text .= "\n{$this->message}";
        }

        return $text;
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return $this->buildMessage();
    }

    public function toMattermost(mixed $notifiable): string
    {
        return $this->buildMessage();
    }
}
