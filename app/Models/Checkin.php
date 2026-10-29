<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    protected $table = 'checkin';
    protected $primaryKey = 'checkin_id';
    public $timestamps = false;

    protected $fillable = [
        'checkin_id',
        'schedule_id',
        'staff_id',
        'checkin_at',
        'checkout_at',
        'duration_min',
        'is_substitute',
        'substituting_for_id',
        'is_kicked',
        // [Check-in ฟีเจอร์ใหม่] คอลัมน์จาก migration 2026_10_30_000004
        'extended_until',
        'extended_at',
        'manual_checkout_required',
        'checkout_reason',
    ];

    // [Check-in ฟีเจอร์ใหม่] เหตุผลการ Check-out (เดิมดูจาก is_kicked ซึ่งปนกัน 2 ความหมาย)
    public const REASON_MANUAL   = 'manual';   // กด Check-out เอง
    public const REASON_AUTO     = 'auto';     // ระบบ Check-out ให้เมื่อหมดเวลากะ
    public const REASON_TAKEOVER = 'takeover'; // มีคนอื่นมา Check-in รับช่วงต่อ
    public const REASON_FORCED   = 'forced';   // หัวหน้ากดบังคับ Check-out

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id', 'schedule_id');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'checkin_id', 'checkin_id');
    }

    // [Check-in ฟีเจอร์ใหม่] คนที่ถูก Check-in ทับ (substituting_for_id เก็บ staff_id ของคนเดิม)
    public function substitutingFor()
    {
        return $this->belongsTo(Staff::class, 'substituting_for_id', 'staff_id');
    }

    public function getRouteKeyName()
    {
        return 'checkin_id';
    }

    public function substituteStaff()
    {
        return $this->belongsToMany(Staff::class, 'substitute_has_staff', 'checkin_id', 'staff_id');
    }
}