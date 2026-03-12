<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_notification_channels', function (Blueprint $table) {
            $table->foreignId('backup_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->primary(['backup_configuration_id', 'notification_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_notification_channels');
    }
};
