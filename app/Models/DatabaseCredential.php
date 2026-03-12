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
        $config = [
            'driver'   => $this->driver,
            'host'     => $this->host,
            'port'     => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'prefix'   => '',
            'strict'   => true,
        ];

        // Driver-specific configurations
        if ($this->driver === 'mysql') {
            $config['charset'] = 'utf8mb4';
            $config['collation'] = 'utf8mb4_unicode_ci';
            $config['engine'] = null;
        } elseif ($this->driver === 'pgsql') {
            $config['charset'] = 'utf8';
            $config['schema'] = 'public';
            $config['sslmode'] = 'prefer';
        } elseif ($this->driver === 'sqlite') {
            $config['foreign_key_constraints'] = true;
        }

        return $config;
    }

    public function backupConfigurations()
    {
        return $this->hasMany(BackupConfiguration::class);
    }
}
