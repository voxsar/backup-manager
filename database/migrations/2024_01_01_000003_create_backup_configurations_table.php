<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('database_credential_id')->constrained()->cascadeOnDelete();
            $table->string('schedule')->default('0 2 * * *'); // cron expression
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status')->nullable(); // success | failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_configurations');
    }
};
