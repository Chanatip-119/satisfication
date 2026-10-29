<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [Check-in ฟีเจอร์ใหม่] เพิ่มคอลัมน์ให้ตาราง checkin
 *
 * - extended_until            : ต่อเวลาถึงกี่โมง (ข้อ 2)
 * - extended_at               : กดต่อเวลาเมื่อไร (ใช้แสดงในไทม์ไลน์ประวัติ)
 * - manual_checkout_required  : ต่อเวลาแล้ว = ต้อง Check-out เอง ระบบจะไม่ Check-out ให้อัตโนมัติ (ข้อ 2)
 * - checkout_reason           : manual / auto / takeover / forced — แยกความหมายที่เดิมปนอยู่ใน is_kicked (ข้อ 3)
 *
 * ไม่แตะข้อมูลเดิม: เพิ่มคอลัมน์ใหม่ทั้งหมดเป็น nullable / มีค่าเริ่มต้น และตรวจ hasColumn ก่อนเพิ่ม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkin', function (Blueprint $table) {
            if (!Schema::hasColumn('checkin', 'extended_until')) {
                $table->timestamp('extended_until')->nullable()->after('checkout_at');
            }
            if (!Schema::hasColumn('checkin', 'extended_at')) {
                $table->timestamp('extended_at')->nullable()->after('extended_until');
            }
            if (!Schema::hasColumn('checkin', 'manual_checkout_required')) {
                $table->boolean('manual_checkout_required')->default(false)->after('extended_at');
            }
            if (!Schema::hasColumn('checkin', 'checkout_reason')) {
                $table->string('checkout_reason', 20)->nullable()->after('manual_checkout_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checkin', function (Blueprint $table) {
            foreach (['checkout_reason', 'manual_checkout_required', 'extended_at', 'extended_until'] as $column) {
                if (Schema::hasColumn('checkin', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};