<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'database_credential_id',
        'schedule',
        'retention_days',
        'enabled',
        'last_run_at',
        'last_status',
    ];

    protected $casts = [
        'enabled'      => 'boolean',
        'retention_days' => 'integer',
        'last_run_at'  => 'datetime',
    ];

    public function databaseCredential()
    {
        return $this->belongsTo(DatabaseCredential::class);
    }

    public function notificationChannels()
    {
        return $this->belongsToMany(NotificationChannel::class, 'backup_notification_channels');
    }
}
