<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class DatabaseCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'port' => 'integer',
    ];

    /**
     * Encrypt password on set.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt password on get.
     */
    public function getPasswordAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    /**
     * Return a Laravel database config array for this credential.
     */
    public function toConnectionConfig(): array
    {
        return [
            'driver'   => $this->driver,
            'host'     => $this->host,
            'port'     => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
            'prefix'   => '',
            'strict'   => true,
            'engine'   => null,
        ];
    }

    public function backupConfigurations()
    {
        return $this->hasMany(BackupConfiguration::class);
    }
}
