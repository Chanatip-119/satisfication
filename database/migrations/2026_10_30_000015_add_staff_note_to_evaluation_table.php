<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * [รายงาน+Online ข้อ 1] คำชี้แจงจากผู้ให้บริการต่อการประเมินแต่ละรายการ
 *
 * - staff_note     : ข้อความชี้แจง (สูงสุด 500 ตัวอักษร ตรวจใน Controller)
 * - staff_note_at  : เวลาที่เขียน
 * - staff_note_by  : staff_id ของผู้เขียน (ไม่ใส่ FK เพื่อไม่ให้การลบบุคลากรติด)
 *
 * เขียนได้ครั้งเดียว: Controller บันทึกด้วยเงื่อนไข "WHERE staff_note IS NULL" จึงเขียนทับไม่ได้
 * ไม่แตะข้อมูลเดิม: เพิ่มคอลัมน์ใหม่แบบ nullable ทั้งหมด
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation', 'staff_note')) {
                $table->text('staff_note')->nullable()->after('comment');
            }
            if (!Schema::hasColumn('evaluation', 'staff_note_at')) {
                $table->timestamp('staff_note_at')->nullable()->after('staff_note');
            }
            if (!Schema::hasColumn('evaluation', 'staff_note_by')) {
                $table->unsignedBigInteger('staff_note_by')->nullable()->after('staff_note_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation', function (Blueprint $table) {
            foreach (['staff_note_by', 'staff_note_at', 'staff_note'] as $column) {
                if (Schema::hasColumn('evaluation', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};