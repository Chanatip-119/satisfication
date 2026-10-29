<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * [เพิ่มใหม่ ระดับ 1 ข้อ 3] PIN ห้ามซ้ำกัน
 * - แก้ PIN ที่ซ้ำหรือไม่ใช่ตัวเลข 4 หลัก (คนที่ staff_id น้อยกว่าได้เก็บ PIN เดิม)
 * - เพิ่ม unique index ให้ staff_pincode
 * คนที่ถูกเปลี่ยน PIN จะถูกบันทึกใน storage/logs/laravel.log (ค้นคำว่า [PIN])
 */
return new class extends Migration
{
    public function up(): void
    {
        $staffs = DB::table('staff')
            ->orderBy('staff_id')
            ->get(['staff_id', 'staff_name', 'staff_pincode']);

        // รอบที่ 1: เก็บ PIN ที่ถูกต้อง (ตัวเลข 4 หลัก) ไว้ให้คนแรกที่ใช้
        $usedPins = [];
        $needNewPin = [];

        foreach ($staffs as $staff) {
            $pin = trim((string) $staff->staff_pincode);

            if (preg_match('/^\d{4}$/', $pin) && !isset($usedPins[$pin])) {
                $usedPins[$pin] = true;
            } else {
                $needNewPin[] = $staff;
            }
        }

        if (count($usedPins) + count($needNewPin) > 10000) {
            throw new RuntimeException('จำนวนบุคลากรเกินจำนวน PIN 4 หลักที่เป็นไปได้ (10,000)');
        }

        // รอบที่ 2: สุ่ม PIN ใหม่ที่ไม่ซ้ำให้คนที่ PIN ซ้ำหรือรูปแบบไม่ถูกต้อง
        foreach ($needNewPin as $staff) {
            do {
                $newPin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            } while (isset($usedPins[$newPin]));

            $usedPins[$newPin] = true;

            DB::table('staff')
                ->where('staff_id', $staff->staff_id)
                ->update(['staff_pincode' => $newPin]);

            Log::warning("[PIN] เปลี่ยน PIN ของ {$staff->staff_name} (staff_id {$staff->staff_id}) จาก \"{$staff->staff_pincode}\" เป็น {$newPin} เพราะซ้ำหรือรูปแบบไม่ถูกต้อง");
        }

        // รอบที่ 3: บังคับที่ระดับ Database ว่า PIN ห้ามซ้ำ
        Schema::table('staff', function (Blueprint $table) {
            $table->unique('staff_pincode', 'staff_staff_pincode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique('staff_staff_pincode_unique');
        });
    }
};