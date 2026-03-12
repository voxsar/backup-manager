<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Testing Wasabi S3 connection...\n";
    
    $disk = Storage::disk('wasabi');
    
    echo "Uploading test file...\n";
    $disk->put('test-connection.txt', 'Connection test at ' . now());
    echo "✓ Test file uploaded successfully!\n";
    
    echo "Retrieving test file...\n";
    $content = $disk->get('test-connection.txt');
    echo "✓ Retrieved content: {$content}\n";
    
    echo "Deleting test file...\n";
    $disk->delete('test-connection.txt');
    echo "✓ Test file deleted\n\n";
    
    echo "✓ Wasabi S3 connection is working correctly!\n";
    exit(0);
} catch (Throwable $e) {
    echo "✗ Error: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
