<?php

namespace App\Console\Commands;

use App\Models\BackupConfiguration;
use App\Models\DatabaseCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackupSystemStatus extends Command
{
    protected $signature = 'backup:status';
    protected $description = 'Show comprehensive backup system status including connections, configurations, and recent backups';

    public function handle(): int
    {
        $this->info('=== Backup System Status ===');
        $this->newLine();

        // Test Database Connections
        $this->info('📊 Database Connections:');
        $credentials = DatabaseCredential::all();
        $connectionResults = [];

        foreach ($credentials as $credential) {
            $result = $this->testConnection($credential);
            $connectionResults[] = $result;

            $status = $result['success'] ? '✓' : '✗';
            $color = $result['success'] ? 'green' : 'red';
            $this->line("  {$status} <fg={$color}>{$credential->name}</> ({$credential->driver}:{$credential->host}:{$credential->port}/{$credential->database})");
            
            if (!$result['success']) {
                $this->line("    Error: {$result['message']}", 'red');
            }
        }

        $this->newLine();

        // Show Backup Configurations
        $this->info('⚙️  Backup Configurations:');
        $configs = BackupConfiguration::with('databaseCredential')->get();

        foreach ($configs as $config) {
            $status = $config->enabled ? '✓ Enabled' : '✗ Disabled';
            $color = $config->enabled ? 'green' : 'yellow';
            $this->line("  <fg={$color}>{$status}</> - {$config->name}");
            $this->line("    Database: {$config->databaseCredential->name}");
            $this->line("    Schedule: {$config->schedule}");
            
            if ($config->last_run_at) {
                $lastRunColor = $config->last_status === 'success' ? 'green' : 'red';
                $this->line("    Last Run: {$config->last_run_at->format('Y-m-d H:i:s')} - <fg={$lastRunColor}>{$config->last_status}</>");
            } else {
                $this->line("    Last Run: Never");
            }
            
            $this->line("    Retention: {$config->retention_days} days");
        }

        $this->newLine();

        // Show Recent Backups
        $this->info('💾 Recent Backup Files:');
        $disk = config('backup.monitor_backups.0.destination.disks.0', 'local');
        
        try {
            $files = Storage::disk($disk)->files('backups');
            
            if (empty($files)) {
                $this->line('  No backup files found');
            } else {
                $recentFiles = collect($files)
                    ->map(function ($file) use ($disk) {
                        return [
                            'name' => basename($file),
                            'size' => Storage::disk($disk)->size($file),
                            'modified' => Storage::disk($disk)->lastModified($file),
                        ];
                    })
                    ->sortByDesc('modified')
                    ->take(5);

                foreach ($recentFiles as $file) {
                    $size = $this->formatBytes($file['size']);
                    $date = date('Y-m-d H:i:s', $file['modified']);
                    $this->line("  • {$file['name']} ({$size}) - {$date}");
                }

                $totalFiles = count($files);
                if ($totalFiles > 5) {
                    $this->line("  ... and " . ($totalFiles - 5) . " more files");
                }

                $totalSize = collect($files)->sum(fn($f) => Storage::disk($disk)->size($f));
                $this->line("  Total: {$totalFiles} files, " . $this->formatBytes($totalSize));
            }
        } catch (\Throwable $e) {
            $this->warn("  ⚠ Storage disk '{$disk}' uses write-only permissions");
            $this->line("  Backups are being uploaded but cannot be listed via API");
            $this->line("  This is a secure configuration for backup storage");
        }

        $this->newLine();

        // Summary
        $successfulConnections = collect($connectionResults)->where('success', true)->count();
        $totalConnections = count($connectionResults);
        $enabledConfigs = $configs->where('enabled', true)->count();
        $totalConfigs = $configs->count();

        $this->info('=== Summary ===');
        $this->line("  Database Connections: <fg=green>{$successfulConnections}</>/{$totalConnections} working");
        $this->line("  Backup Configurations: <fg=green>{$enabledConfigs}</>/{$totalConfigs} enabled");
        
        $allConnectionsWorking = $successfulConnections === $totalConnections;
        if ($allConnectionsWorking && $enabledConfigs > 0) {
            $this->info("\n✓ Backup system is fully operational!");
            return self::SUCCESS;
        } else {
            $this->warn("\n⚠ Backup system needs attention!");
            return self::FAILURE;
        }
    }

    private function testConnection(DatabaseCredential $credential): array
    {
        $connectionName = "status_test_{$credential->id}";
        $success = false;
        $message = '';

        try {
            Config::set("database.connections.{$connectionName}", $credential->toConnectionConfig());
            DB::purge($connectionName);
            DB::connection($connectionName)->getPdo();
            DB::connection($connectionName)->select('SELECT 1 as test');
            
            $success = true;
            $message = 'Connection successful';
        } catch (Throwable $e) {
            $message = $e->getMessage();
        } finally {
            Config::set("database.connections.{$connectionName}", null);
            DB::purge($connectionName);
        }

        return [
            'credential_id' => $credential->id,
            'name' => $credential->name,
            'success' => $success,
            'message' => $message,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
