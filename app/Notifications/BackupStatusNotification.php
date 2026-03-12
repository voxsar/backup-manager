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
        private ?string $databaseName = null,
        private ?string $backupSize = null,
        private ?float $duration = null,
        private ?string $backupPath = null,
        private ?int $totalBackups = null,
        private ?string $totalSize = null,
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

    public function toMattermost(mixed $notifiable): array
    {
        // Build fields array
        $fields = [];
        
        if ($this->databaseName) {
            $fields[] = [
                'title' => 'Database',
                'value' => $this->databaseName,
                'short' => true,
            ];
        }
        
        if ($this->backupSize) {
            $fields[] = [
                'title' => 'Backup Size',
                'value' => $this->backupSize,
                'short' => true,
            ];
        }
        
        if ($this->duration !== null) {
            $fields[] = [
                'title' => 'Duration',
                'value' => number_format($this->duration, 2) . 's',
                'short' => true,
            ];
        }
        
        if ($this->status === 'success') {
            $fields[] = [
                'title' => 'Storage',
                'value' => 'Wasabi S3 (ap-southeast-1)',
                'short' => true,
            ];
        }
        
        if ($this->totalBackups !== null) {
            $fields[] = [
                'title' => 'Total Backups',
                'value' => (string) $this->totalBackups,
                'short' => true,
            ];
        }
        
        if ($this->totalSize) {
            $fields[] = [
                'title' => 'Total Storage Used',
                'value' => $this->totalSize,
                'short' => true,
            ];
        }
        
        if ($this->backupPath) {
            $fields[] = [
                'title' => 'Path',
                'value' => $this->backupPath,
                'short' => false,
            ];
        }

        // Return Slack-compatible attachment format
        return [
            'attachments' => [[
                'color' => $this->status === 'success' ? '#36a64f' : '#ff0000',
                'title' => $this->status === 'success' ? '✅ Backup Successful' : '❌ Backup Failed',
                'text' => $this->message ?: $this->backupName,
                'fields' => $fields,
                'footer' => 'Backup Manager',
                'ts' => time(),
            ]],
        ];
    }
}
