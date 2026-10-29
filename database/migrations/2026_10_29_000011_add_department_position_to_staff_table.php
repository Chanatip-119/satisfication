<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [เพิ่มใหม่ ระดับ 4] เพิ่มคอลัมน์ department (ฝ่าย) และ position (ตำแหน่ง) ให้ตาราง staff
 * โค้ดใช้ 2 คอลัมน์นี้ แต่ไม่มี migration สร้างไว้ (เพิ่มเองใน DB)
 * ปลอดภัยกับ DB ที่มีคอลัมน์อยู่แล้ว: ตรวจ hasColumn ก่อน จึงข้ามไปโดยไม่แตะข้อมูลเดิม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'department')) {
                $table->string('department')->nullable()->after('role_id');
            }
            if (!Schema::hasColumn('staff', 'position')) {
                $table->string('position')->nullable()->after('department');
            }
        });
    }

    public function down(): void
    {
        // ตั้งใจไม่ลบคอลัมน์ตอน rollback
        // เพราะใน DB ที่ใช้งานอยู่ 2 คอลัมน์นี้มีอยู่ก่อนแล้ว ถ้าลบ ข้อมูลฝ่ายและตำแหน่งจะหายทั้งหมด
    }
};