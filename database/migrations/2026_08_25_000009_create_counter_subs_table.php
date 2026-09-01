<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter_sub', function (Blueprint $table) {
            $table->id('counter_sub_id');
            $table->unsignedBigInteger('counter_id'); 
            $table->boolean('is_active')->default(true);

            $table->foreign('counter_id')
                  ->references('counter_id')
                  ->on('counter')
                  ->cascadeOnDelete();
        }); 
        Schema::table('schedule', function (Blueprint $table) {
            if (!Schema::hasColumn('schedule', 'counter_sub_id')) {
                $table->unsignedBigInteger('counter_sub_id')->nullable()->after('schedule_id');
                $table->foreign('counter_sub_id')
                      ->references('counter_sub_id')
                      ->on('counter_sub')
                      ->cascadeOnDelete();
            }
        });
        Schema::table('checkin', function (Blueprint $table) {
            if (!Schema::hasColumn('checkin', 'staff_id')) {
                $table->unsignedBigInteger('staff_id')->nullable()->after('schedule_id');
                $table->foreign('staff_id')
                      ->references('staff_id')
                      ->on('staff')
                      ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');
        });

        Schema::table('schedule', function (Blueprint $table) {
            $table->dropForeign(['counter_sub_id']);
            $table->dropColumn('counter_sub_id');
        });
        
        Schema::dropIfExists('counter_sub');
    }
};