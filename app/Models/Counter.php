<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    protected $table = 'counter';
    protected $primaryKey = 'counter_id';
    public $timestamps = false;

    protected $fillable = [
        'counter_id',
        'counter_location',
        'is_active',
        'is_online', // [รายงาน+Online ข้อ 4] เคาน์เตอร์ประเมิน Online (ไม่ต้องมีคน Check-in)
    ];

    // [รายงาน+Online ข้อ 4] ให้ is_online เป็น true/false เสมอ (ใน DB เป็น 0/1)
    protected $casts = [
        'is_online' => 'boolean',
    ];

    public function countersubs()
    {
        // [แก้ไข ระดับ 4] เรียงจุดย่อยตาม counter_sub_id เสมอ
        // เดิม: ไม่ได้กำหนดลำดับ (ขึ้นกับ Database) หน้าที่นับลำดับเองอย่างหน้าเคาน์เตอร์และแดชบอร์ด
        //       อาจได้ "1.1" / "1.2" สลับกันกับหน้าอื่น
        // ตอนนี้ลำดับตรงกับ CounterSub::codeMap() ที่ใช้สร้างชื่อเคาน์เตอร์ทั้งระบบ
        return $this->hasMany(CounterSub::class, 'counter_id', 'counter_id')->orderBy('counter_sub_id');
    }

    public function getRouteKeyName()
    {
        return 'counter_id';
    }
}