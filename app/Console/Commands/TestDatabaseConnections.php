<?php

namespace App\Console\Commands;

use App\Models\DatabaseCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestDatabaseConnections extends Command
{
    protected $signature = 'backup:test-connections {--id= : Test a specific database credential by ID}';
    protected $description = 'Test all database connections (or a specific one by --id)';

    public function handle(): int
    {
        $query = DatabaseCredential::query();

        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $credentials = $query->get();

        if ($credentials->isEmpty()) {
            $this->error('No database credentials found to test.');
            return self::FAILURE;
        }

        $this->info("Testing {$credentials->count()} database connection(s)...\n");

        $results = [];
        foreach ($credentials as $credential) {
            $result = $this->testConnection($credential);
            $results[] = $result;
        }

        // Summary
        $this->newLine();
        $this->info('=== Test Summary ===');
        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();

        $this->info("✓ Successful: {$successful}");
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function testConnection(DatabaseCredential $credential): array
    {
        $this->info("Testing: {$credential->name} (ID: {$credential->id})");
        $this->line("  Driver: {$credential->driver}");
        $this->line("  Host: {$credential->host}:{$credential->port}");
        $this->line("  Database: {$credential->database}");
        $this->line("  Username: {$credential->username}");

        $connectionName = "test_connection_{$credential->id}";
        $success = false;
        $message = '';

        try {
            // Register dynamic database connection
            Config::set("database.connections.{$connectionName}", $credential->toConnectionConfig());
            DB::purge($connectionName);

            // Test the connection
            DB::connection($connectionName)->getPdo();
            
            // Try a simple query
            $result = DB::connection($connectionName)->select('SELECT 1 as test');
            
            if (!empty($result)) {
                $this->info("  ✓ Connection successful!\n");
                $success = true;
                $message = 'Connection successful';
            } else {
                throw new \RuntimeException('Query returned empty result');
            }
        } catch (Throwable $e) {
            $this->error("  ✗ Connection failed!");
            $this->error("  Error: {$e->getMessage()}\n");
            $message = $e->getMessage();
        } finally {
            // Clean up the connection
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
}
