<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * [รายงาน+Online ข้อ 4] รองรับเคาน์เตอร์ Online (รับประเมินโดยไม่มีคน Check-in)
 *
 * 1. counter.is_online              : เคาน์เตอร์ประเภท Online
 * 2. evaluation.counter_sub_id      : การประเมินนี้เป็นของจุดย่อยไหน (ใหม่)
 *    - การประเมิน Online ไม่มี checkin_id จึงต้องรู้จุดจากคอลัมน์นี้
 *    - เติมค่าให้ข้อมูลเดิมทุกแถวจาก checkin → schedule (เพิ่มข้อมูล ไม่ลบอะไร)
 *    - รายงานใช้คอลัมน์นี้คอลัมน์เดียวได้ทุกกรณี ไม่ต้อง JOIN ผ่าน checkin
 * 3. evaluation.checkin_id          : เปลี่ยนเป็น NULL ได้ (การประเมิน Online ไม่มีคน Check-in)
 *    - ชนิดคงเดิม BIGINT UNSIGNED และ FK evaluation_checkin_id_foreign (CASCADE) คงเดิม
 *
 * ไม่ลบหรือแก้ค่าข้อมูลเดิม: มีแค่เพิ่มคอลัมน์ เติมค่าในคอลัมน์ใหม่ และยอมให้ checkin_id ว่างได้
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) คอลัมน์ใหม่
        Schema::table('counter', function (Blueprint $table) {
            if (!Schema::hasColumn('counter', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('is_active');
            }
        });

        if (!Schema::hasColumn('evaluation', 'counter_sub_id')) {
            Schema::table('evaluation', function (Blueprint $table) {
                $table->unsignedBigInteger('counter_sub_id')->nullable()->after('checkin_id');
                $table->index('counter_sub_id', 'evaluation_counter_sub_id_index');
                // ลบจุดย่อย → การประเมินของจุดนั้นถูกลบตาม (เหมือนพฤติกรรมเดิมใน CounterController)
                $table->foreign('counter_sub_id', 'evaluation_counter_sub_id_foreign')
                    ->references('counter_sub_id')->on('counter_sub')
                    ->cascadeOnDelete();
            });
        }

        // 2) เติม counter_sub_id ให้การประเมินเดิมทุกแถว จาก checkin → schedule
        $filled = DB::update("
            UPDATE evaluation
            SET counter_sub_id = (
                SELECT s.counter_sub_id
                FROM checkin c
                JOIN schedule s ON s.schedule_id = c.schedule_id
                WHERE c.checkin_id = evaluation.checkin_id
            )
            WHERE counter_sub_id IS NULL AND checkin_id IS NOT NULL
        ");
        Log::info("[EVALUATION] เติม counter_sub_id ให้การประเมินเดิม {$filled} แถว");

        // 3) checkin_id เป็น NULL ได้ (ชนิดเดิม BIGINT UNSIGNED, FK เดิมยังอยู่)
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `evaluation` MODIFY `checkin_id` BIGINT UNSIGNED NULL');
        } else {
            Schema::table('evaluation', function (Blueprint $table) {
                $table->unsignedBigInteger('checkin_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // กันข้อมูลหาย: ถ้ามีการประเมิน Online แล้ว (checkin_id ว่าง) จะย้อนกลับไม่ได้
        // เพราะคอลัมน์ checkin_id แบบเดิมไม่ยอมให้ว่าง ต้องลบการประเมินเหล่านั้นทิ้งก่อน
        $onlineCount = DB::table('evaluation')->whereNull('checkin_id')->count();
        if ($onlineCount > 0) {
            throw new RuntimeException(
                "ย้อนกลับไม่ได้: มีการประเมินจากเคาน์เตอร์ Online {$onlineCount} รายการ (checkin_id ว่าง) "
                . 'ซึ่งจะต้องถูกลบทิ้งถ้าย้อนกลับ — ยกเลิกการ rollback เพื่อไม่ให้ข้อมูลหาย'
            );
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `evaluation` MODIFY `checkin_id` BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('evaluation', 'counter_sub_id')) {
            Schema::table('evaluation', function (Blueprint $table) {
                $table->dropForeign('evaluation_counter_sub_id_foreign');
                $table->dropIndex('evaluation_counter_sub_id_index');
                $table->dropColumn('counter_sub_id');
            });
        }

        Schema::table('counter', function (Blueprint $table) {
            if (Schema::hasColumn('counter', 'is_online')) {
                $table->dropColumn('is_online');
            }
        });
    }
};