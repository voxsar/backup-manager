<?php

namespace App\Console\Commands;

use App\Models\BackupConfiguration;
use App\Models\NotificationChannel;
use App\Notifications\BackupStatusNotification;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunScheduledBackups extends Command
{
    protected $signature   = 'backup:run-scheduled {--id= : Run a specific backup configuration by ID}';
    protected $description = 'Run all due backup configurations (or a specific one by --id)';

    public function handle(): int
    {
        $query = BackupConfiguration::with(['databaseCredential', 'notificationChannels'])
            ->where('enabled', true);

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $configs = $query->get();

        foreach ($configs as $config) {
            if ($this->option('id') || $this->isDue($config)) {
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

            $safeName    = preg_replace('/[^a-z0-9_-]/i', '_', $credential->database);
            $filename    = "{$safeName}_".now()->format('Y-m-d_H-i-s').'.sql';
            $storagePath = 'backups/'.$filename;

            // Dump database to tmp file, then persist to storage disk
            $this->dumpDatabase($credential, $storagePath);

            $config->update(['last_run_at' => now(), 'last_status' => 'success']);
            $this->pruneOldBackups($safeName, $config->retention_days);

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

    private function dumpDatabase(mixed $credential, string $storagePath): void
    {
        $driver = $credential->driver;

        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            throw new \RuntimeException("Unsupported driver: {$driver}");
        }

        // Secure unique temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'bkp_backup_').'.sql';

        try {
            if ($driver === 'mysql') {
                // Write credentials to a temp options file so they never appear
                // in process listings (avoids --password= on the CLI)
                $optionsFile = tempnam(sys_get_temp_dir(), 'mysql_opts_');
                file_put_contents($optionsFile,
                    "[client]\npassword=".str_replace('"', '\\"', $credential->password)."\n");
                chmod($optionsFile, 0600);

                $cmd = sprintf(
                    'mysqldump --defaults-extra-file=%s --host=%s --port=%d --user=%s --single-transaction --routines --triggers %s > %s 2>&1',
                    escapeshellarg($optionsFile),
                    escapeshellarg($credential->host),
                    (int) $credential->port,
                    escapeshellarg($credential->username),
                    escapeshellarg($credential->database),
                    escapeshellarg($tmpFile)
                );
            } elseif ($driver === 'pgsql') {
                // PGPASSWORD is the recommended secure approach for pg_dump
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
                $cmd = sprintf(
                    'sqlite3 %s .dump > %s 2>&1',
                    escapeshellarg($credential->database),
                    escapeshellarg($tmpFile)
                );
            }

            exec($cmd, $output, $returnCode);

            if (isset($optionsFile)) {
                @unlink($optionsFile);
            }

            if ($returnCode !== 0) {
                throw new \RuntimeException("Dump command failed (exit {$returnCode})");
            }

            $disk = config('backup.monitor_backups.0.destination.disks.0', 'local');
            Storage::disk($disk)->put($storagePath, file_get_contents($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    private function pruneOldBackups(string $safeDatabaseName, int $retentionDays): void
    {
        if ($retentionDays <= 0) {
            return;
        }

        $disk   = Storage::disk(config('backup.monitor_backups.0.destination.disks.0', 'local'));
        $prefix = 'backups/'.$safeDatabaseName.'_';
        $cutoff = now()->subDays($retentionDays)->timestamp;

        foreach ($disk->files('backups') as $file) {
            if (str_starts_with($file, $prefix) && $disk->lastModified($file) < $cutoff) {
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

            // Anonymous notifiable carrying channel-specific config
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

                public function notify(mixed $notification): void
                {
                    $channels = $notification->via($this);
                    foreach ($channels as $channelClass) {
                        app($channelClass)->send($this, $notification);
                    }
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
