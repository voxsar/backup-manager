<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class NotificationChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'config',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Encrypt the config JSON on set.
     */
    public function setConfigAttribute(array $value): void
    {
        $this->attributes['config'] = Crypt::encryptString(json_encode($value));
    }

    /**
     * Decrypt and decode config on get.
     */
    public function getConfigAttribute(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return json_decode(Crypt::decryptString($value), true) ?? [];
    }

    public function backupConfigurations()
    {
        return $this->belongsToMany(BackupConfiguration::class, 'backup_notification_channels');
    }
}
