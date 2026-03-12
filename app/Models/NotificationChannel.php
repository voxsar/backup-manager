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
     * Always store config as encrypted JSON.
     * Accepts either an array (from the Filament form) or a plain string.
     */
    public function setConfigAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $this->attributes['config'] = Crypt::encryptString(json_encode($value));
        } elseif (is_string($value)) {
            // Check if it's already an encrypted payload (starts with eyJ = base64 JSON)
            try {
                Crypt::decryptString($value);
                // Already encrypted — store as-is
                $this->attributes['config'] = $value;
            } catch (\Illuminate\Contracts\Encryption\DecryptException) {
                // Plain JSON string — encrypt it
                $this->attributes['config'] = Crypt::encryptString($value);
            }
        }
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
