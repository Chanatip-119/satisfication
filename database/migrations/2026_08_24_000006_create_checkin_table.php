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
        Schema::create('checkin', function (Blueprint $table) {
            $table->id('checkin_id');
            $table->foreignId('staff_id')->constrained('staff', 'staff_id');
            $table->foreignId('schedule_id')->constrained('schedule','schedule_id');
            $table->timestamp('checkin_at');
            $table->timestamp('checkout_at')->nullable();
            $table->integer('duration_min')->nullable();
            $table->boolean('is_substitute')->nullable();
            $table->unsignedBigInteger('substituting_for_id')->nullable();
            $table->boolean('is_kicked')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkin');
    }
};
