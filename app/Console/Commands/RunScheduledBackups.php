<?php

namespace App\Console\Commands;

use App\Models\BackupConfiguration;
use App\Models\NotificationChannel;
use App\Notifications\BackupStatusNotification;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunScheduledBackups extends Command
{
    protected $signature   = 'backup:run-scheduled';
    protected $description = 'Run all due backup configurations';

    public function handle(): int
    {
        $configs = BackupConfiguration::with(['databaseCredential', 'notificationChannels'])
            ->where('enabled', true)
            ->get();

        foreach ($configs as $config) {
            if ($this->isDue($config)) {
                $this->runBackup($config);
            }
        }

        return self::SUCCESS;
    }

    private function isDue(BackupConfiguration $config): bool
    {
        $cron = new CronExpression($config->schedule);

        if (! $config->last_run_at) {
            return true;
        }

        return $cron->getNextRunDate($config->last_run_at)->isPast();
    }

    private function runBackup(BackupConfiguration $config): void
    {
        $credential = $config->databaseCredential;

        if (! $credential) {
            $this->error("No credential for backup config #{$config->id}");
            return;
        }

        $connectionName = "backup_dynamic_{$config->id}";

        try {
            // Register dynamic database connection
            Config::set("database.connections.{$connectionName}", $credential->toConnectionConfig());
            DB::purge($connectionName);

            // Create backup filename
            $filename = sprintf(
                '%s_%s.sql',
                preg_replace('/[^a-z0-9_-]/i', '_', $credential->database),
                now()->format('Y-m-d_H-i-s')
            );
            $path = 'backups/'.$filename;

            // Dump database
            $this->dumpDatabase($connectionName, $credential, $path);

            // Mark success
            $config->update(['last_run_at' => now(), 'last_status' => 'success']);

            // Prune old backups
            $this->pruneOldBackups($credential->database, $config->retention_days);

            $this->info("Backup successful: {$filename}");

            $this->notify($config, 'success', "File: {$filename}");
        } catch (Throwable $e) {
            $config->update(['last_run_at' => now(), 'last_status' => 'failed']);
            $this->error("Backup failed for config #{$config->id}: {$e->getMessage()}");
            $this->notify($config, 'failed', $e->getMessage());
        } finally {
            Config::set("database.connections.{$connectionName}", null);
            DB::purge($connectionName);
        }
    }

    private function dumpDatabase(string $connection, mixed $credential, string $storagePath): void
    {
        $driverMap = [
            'mysql'  => 'mysqldump',
            'pgsql'  => 'pg_dump',
            'sqlite' => 'sqlite3',
        ];

        $driver = $credential->driver;

        if (! isset($driverMap[$driver])) {
            throw new \RuntimeException("Unsupported driver: {$driver}");
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'bkp_').'.'.$driver.'.sql';

        try {
            if ($driver === 'mysql') {
                $cmd = sprintf(
                    'mysqldump --host=%s --port=%d --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
                    escapeshellarg($credential->host),
                    (int) $credential->port,
                    escapeshellarg($credential->username),
                    escapeshellarg($credential->password),
                    escapeshellarg($credential->database),
                    escapeshellarg($tmpFile)
                );
            } elseif ($driver === 'pgsql') {
                $cmd = sprintf(
                    'PGPASSWORD=%s pg_dump --host=%s --port=%d --username=%s %s > %s 2>&1',
                    escapeshellarg($credential->password),
                    escapeshellarg($credential->host),
                    (int) $credential->port,
                    escapeshellarg($credential->username),
                    escapeshellarg($credential->database),
                    escapeshellarg($tmpFile)
                );
            } else {
                // SQLite
                $cmd = sprintf(
                    'sqlite3 %s .dump > %s 2>&1',
                    escapeshellarg($credential->database),
                    escapeshellarg($tmpFile)
                );
            }

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException("Dump command failed (exit {$returnCode})");
            }

            // Store in configured disk
            Storage::disk(config('backup.destination.disks.0', 'local'))
                ->put($storagePath, file_get_contents($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    private function pruneOldBackups(string $database, int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $disk    = Storage::disk(config('backup.destination.disks.0', 'local'));
        $prefix  = 'backups/'.preg_replace('/[^a-z0-9_-]/i', '_', $database).'_';
        $cutoff  = now()->subDays($retentionDays)->timestamp;

        foreach ($disk->files('backups') as $file) {
            if (str_starts_with(basename($file), basename($prefix))
                && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    private function notify(BackupConfiguration $config, string $status, string $message): void
    {
        foreach ($config->notificationChannels as $channel) {
            if (! $channel->enabled) {
                continue;
            }

            // Create a notifiable object carrying the channel config
            $notifiable = new class($channel) {
                public string $type;
                public array  $channel_config;

                public function __construct(NotificationChannel $ch)
                {
                    $this->type           = $ch->type;
                    $this->channel_config = $ch->config;
                }

                public function routeNotificationFor(string $driver): mixed
                {
                    return $this->channel_config;
                }
            };

            try {
                $notifiable->notify(
                    new BackupStatusNotification($config->name, $status, $message)
                );
            } catch (Throwable $e) {
                $this->warn("Notification failed: {$e->getMessage()}");
            }
        }
    }
}
