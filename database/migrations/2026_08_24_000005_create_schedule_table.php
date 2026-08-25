<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->date('schedule_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['checkin', 'waiting', 'empty', 'sick', 'close'])->default('empty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule');
    }
};
