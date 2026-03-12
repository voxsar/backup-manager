<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_configurations', function (Blueprint $table) {
            $table->unsignedInteger('total_backups')->default(0)->after('last_status');
            $table->unsignedBigInteger('total_size_bytes')->default(0)->after('total_backups');
        });
    }

    public function down(): void
    {
        Schema::table('backup_configurations', function (Blueprint $table) {
            $table->dropColumn(['total_backups', 'total_size_bytes']);
        });
    }
};
