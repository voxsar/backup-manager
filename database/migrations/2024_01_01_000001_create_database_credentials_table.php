<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver')->default('mysql');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(3306);
            $table->string('database');
            $table->string('username');
            $table->text('password'); // encrypted
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_credentials');
    }
};
