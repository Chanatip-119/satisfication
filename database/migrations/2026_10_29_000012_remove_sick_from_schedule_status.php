<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * [เพิ่มใหม่ ระดับ 4] ตัดสถานะ 'sick' ออกจาก schedule.status
 * ระบบลาป่วยถูกนำออกจากหน้าเว็บแล้ว แต่ enum ใน Database ยังมีค่า 'sick'
 * - เปลี่ยนแถวที่เป็น 'sick' ให้เป็น 'empty' (แถวไม่หาย)
 * - ตัด 'sick' ออกจาก enum (ใช้ได้กับ MySQL / MariaDB)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('schedule')->where('status', 'sick')->update(['status' => 'empty']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `schedule` MODIFY `status` ENUM('checkin', 'waiting', 'empty', 'close') NOT NULL DEFAULT 'empty'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `schedule` MODIFY `status` ENUM('checkin', 'waiting', 'empty', 'sick', 'close') NOT NULL DEFAULT 'empty'");
        }
    }
};