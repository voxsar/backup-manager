<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TestWasabiConnection extends Command
{
    protected $signature = 'backup:test-wasabi';
    protected $description = 'Test Wasabi S3 connection';

    public function handle(): int
    {
        $this->info('Testing Wasabi S3 connection...');

        try {
            $disk = Storage::disk('wasabi');
            
            $this->line('Uploading test file...');
            $testContent = 'Wasabi connection test at ' . now()->toDateTimeString();
            $disk->put('test-connection.txt', $testContent);
            $this->info('✓ Test file uploaded successfully');
            
            $this->line('Retrieving test file...');
            $retrieved = $disk->get('test-connection.txt');
            $this->info("✓ Retrieved content: {$retrieved}");
            
            $this->line('Deleting test file...');
            $disk->delete('test-connection.txt');
            $this->info('✓ Test file deleted');
            
            $this->newLine();
            $this->info('✓ Wasabi S3 connection is working correctly!');
            
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('✗ Connection failed!');
            $this->error("Error: {$e->getMessage()}");
            $this->line("File: {$e->getFile()}:{$e->getLine()}");
            return self::FAILURE;
        }
    }
}
