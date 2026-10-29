<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounterSub extends Model
{
    protected $table = 'counter_sub';
    protected $primaryKey = 'counter_sub_id';
    public $timestamps = false;

    protected $fillable = [
        'counter_sub_id',
        'counter_id',
        'is_active',
    ];
    public function counter()
    {
        return $this->belongsTo(Counter::class, 'counter_id', 'counter_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'counter_sub_id', 'counter_sub_id');
    }

    public function getRouteKeyName()
    {
        // [แก้ไข ระดับ 4] เดิม: return 'counter_id'; (copy มาจาก Counter)
        // ถ้ามี route ที่ใช้ Route Model Binding กับ CounterSub จะค้นด้วย counter_id แทน
        // เช่น เปิด /…/5 แล้วได้ "จุดย่อยแรกของเคาน์เตอร์ 5" แทน "จุดย่อย ID 5"
        return 'counter_sub_id';
    }

    // ===== [เพิ่มใหม่ ระดับ 4] ชื่อเคาน์เตอร์รูปแบบเดียวกันทั้งระบบ =====
    // เดิม: แต่ละหน้าสร้างชื่อเองคนละแบบ จุดย่อยเดียวกันจึงมีถึง 5 ชื่อ
    //   หน้าจัดการเคาน์เตอร์ / แดชบอร์ด : "1.1"            (counter_id.ลำดับ)
    //   หน้า Check-in / รายงานเคาน์เตอร์: "1.7"            (counter_id.counter_sub_id)
    //   หน้าประเมิน / QR Code / CSV     : "เคาน์เตอร์ 7"   (counter_sub_id)
    //   Side Panel บุคลากร              : "1.{sub_id}"     (ขึ้น 1 ทุกเคาน์เตอร์)
    //   หน้าตาราง                      : "จุดบริการที่ 1"
    // ใหม่: ใช้ "counter_id.ลำดับจุดย่อย" (เช่น 8.1) ทุกหน้า ตามที่แอดมินเห็นในหน้าจัดการเคาน์เตอร์
    //       ลำดับเรียงตาม counter_sub_id (Counter::countersubs() ก็เรียงแบบเดียวกันแล้ว)

    /** @var array<int,string>|null เก็บผลไว้ใช้ซ้ำภายในคำขอ (request) เดียว */
    protected static ?array $codeMapCache = null;

    /**
     * ตาราง counter_sub_id => รหัสแสดงผล เช่น [7 => '1.1', 9 => '1.2', 12 => '8.1']
     */
    public static function codeMap(): array
    {
        if (static::$codeMapCache === null) {
            $map = [];
            $grouped = static::query()
                ->orderBy('counter_sub_id')
                ->get(['counter_sub_id', 'counter_id'])
                ->groupBy('counter_id');

            foreach ($grouped as $counterId => $subs) {
                foreach ($subs->values() as $index => $sub) {
                    $map[$sub->counter_sub_id] = $counterId . '.' . ($index + 1);
                }
            }
            static::$codeMapCache = $map;
        }

        return static::$codeMapCache;
    }

    /**
     * รหัสแสดงผลของจุดย่อย เช่น "8.1" (ไม่พบคืน null)
     */
    public static function codeFor($counterSubId): ?string
    {
        return static::codeMap()[(int) $counterSubId] ?? null;
    }

    /**
     * ข้อความพร้อมแสดง เช่น "เคาน์เตอร์ 8.1" (ไม่พบคืน "ไม่ระบุ")
     */
    public static function labelFor($counterSubId): string
    {
        $code = static::codeFor($counterSubId);
        return $code ? 'เคาน์เตอร์ ' . $code : 'ไม่ระบุ';
    }

    /**
     * ล้างค่าที่เก็บไว้ (เรียกหลังเพิ่ม/ลบจุดย่อยภายในคำขอเดียวกัน ถ้าต้องการใช้ค่าใหม่ทันที)
     */
    public static function clearCodeMapCache(): void
    {
        static::$codeMapCache = null;
    }
    // ===== [จบ ระดับ 4: ชื่อเคาน์เตอร์] =====
}