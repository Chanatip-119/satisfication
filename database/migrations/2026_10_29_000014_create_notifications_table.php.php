<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Check-in ฟีเจอร์ใหม่ ข้อ 1] ตารางเก็บการแจ้งเตือนในเว็บ
 * โครงสร้างมาตรฐานของ Laravel Database Notifications
 * แต่ละแถว = แจ้งเตือน 1 รายการของผู้รับ 1 คน (เก็บสถานะอ่านแล้ว/ยังไม่อ่านแยกรายคน)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};