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
use Illuminate\Support\Facades\Log;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Spatie\DbDumper\Compressors\GzipCompressor;
use ZipArchive;
use Throwable;

class RunScheduledBackups extends Command
{
    protected $signature   = 'backup:run-scheduled {--id= : Run a specific backup configuration by ID}';
    protected $description = 'Run all due backup configurations using Spatie DbDumper (or a specific one by --id)';

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
        $safeName = preg_replace('/[^a-z0-9_-]/i', '_', $credential->database);

        $startTime = microtime(true);
        $backupSize = null;
        $backupSizeBytes = 0;
        $storagePath = null;

        try {
            // Register dynamic database connection
            Config::set("database.connections.{$connectionName}", $credential->toConnectionConfig());
            DB::purge($connectionName);

            $backupName = "{$safeName}_" . now()->format('Y-m-d_H-i-s');
            
            // Create database dump using Spatie's DbDumper
            $dumpFilePath = $this->createDatabaseDump($credential,  $backupName);

            // Create ZIP archive (like Spatie Backup does)
            $zipFilePath = $this->createZipArchive($dumpFilePath, $backupName);

            // Get backup size
            $backupSizeBytes = filesize($zipFilePath);
            $backupSize = $this->formatBytes($backupSizeBytes);

            // Upload to storage
            $storagePath = "backups/{$safeName}/{$backupName}.zip";
            $disk = config('backup.monitor_backups.0.destination.disks.0', 'wasabi');
            Storage::disk($disk)->put($storagePath, file_get_contents($zipFilePath));

            // Cleanup temp files
            @unlink($dumpFilePath);
            @unlink($zipFilePath);

            $duration = microtime(true) - $startTime;

            // Update statistics
            $config->increment('total_backups');
            $config->increment('total_size_bytes', $backupSizeBytes);
            $config->update(['last_run_at' => now(), 'last_status' => 'success']);
            
            // Reload to get updated stats
            $config->refresh();

            $this->info("Backup successful: {$backupName}.zip");
            $this->notify(
                $config, 
                'success', 
                "Backup completed successfully",
                $credential->database,
                $backupSize,
                $duration,
                $storagePath,
                $config->total_backups,
                $this->formatBytes($config->total_size_bytes)
            );
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;
            $config->update(['last_run_at' => now(), 'last_status' => 'failed']);
            $this->error("Backup failed for config #{$config->id}: {$e->getMessage()}");
            $this->notify(
                $config, 
                'failed', 
                $e->getMessage(),
                $credential->database,
                null,
                $duration,
                null,
                $config->total_backups,
                $this->formatBytes($config->total_size_bytes)
            );
            Log::error("Backup failed for config #{$config->id}", ['exception' => $e]);
        } finally {
            Config::set("database.connections.{$connectionName}", null);
            DB::purge($connectionName);
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function createDatabaseDump($credential, string $backupName): string
    {
        $dumpFile = sys_get_temp_dir() . '/' . $backupName . '.sql';

        // Use Spatie's DbDumper classes
        if ($credential->driver === 'mysql') {
            MySql::create()
                ->setDbName($credential->database)
                ->setUserName($credential->username)
                ->setPassword($credential->password)
                ->setHost($credential->host)
                ->setPort($credential->port)
                ->addExtraOption('--single-transaction')
                ->addExtraOption('--routines')
                ->addExtraOption('--triggers')
                ->dumpToFile($dumpFile);
        } elseif ($credential->driver === 'pgsql') {
            PostgreSql::create()
                ->setDbName($credential->database)
                ->setUserName($credential->username)
                ->setPassword($credential->password)
                ->setHost($credential->host)
                ->setPort($credential->port)
                ->dumpToFile($dumpFile);
        } elseif ($credential->driver === 'sqlite') {
            Sqlite::create()
                ->setDbName($credential->database)
                ->dumpToFile($dumpFile);
        } else {
            throw new \RuntimeException("Unsupported database driver: {$credential->driver}");
        }

        // Verify dump file was created
        if (!file_exists($dumpFile)) {
            throw new \RuntimeException("Database dump file was not created: {$dumpFile}");
        }

        // Compress with gzip
        $compressedFile = $dumpFile . '.gz';
        exec("gzip -c " . escapeshellarg($dumpFile) . " > " . escapeshellarg($compressedFile), $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($compressedFile)) {
            throw new \RuntimeException("Failed to compress dump file");
        }

        // Remove uncompressed file
        @unlink($dumpFile);

        return $compressedFile;
    }

    private function createZipArchive(string $dumpFile, string $backupName): string
    {
        $zipFile = sys_get_temp_dir() . '/' . $backupName . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException("Could not create ZIP archive");
        }

        // Add the compressed SQL dump to the ZIP
        $zip->addFile($dumpFile, 'db-dumps/' . basename($dumpFile));
        
        // Add manifest file (like Spatie does)
        $manifest = [
            'backup_name' => $backupName,
            'created_at' => now()->toIso8601String(),
            'database' => basename($dumpFile),
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        
        $zip->close();

        return $zipFile;
    }

    private function notify(
        BackupConfiguration $config, 
        string $status, 
        string $message,
        ?string $databaseName = null,
        ?string $backupSize = null,
        ?float $duration = null,
        ?string $backupPath = null,
        ?int $totalBackups = null,
        ?string $totalSize = null
    ): void
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
                    new BackupStatusNotification(
                        $config->name, 
                        $status, 
                        $message,
                        $databaseName,
                        $backupSize,
                        $duration,
                        $backupPath,
                        $totalBackups,
                        $totalSize
                    )
                );
            } catch (Throwable $e) {
                $this->warn("Notification failed: {$e->getMessage()}");
            }
        }
    }
}
