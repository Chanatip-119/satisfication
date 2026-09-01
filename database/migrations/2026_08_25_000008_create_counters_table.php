<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter', function (Blueprint $table) {
            $table->id('counter_id');
            $table->string('counter_location', 255);
            $table->boolean('is_active')->default(true);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('counter');
    }
};
