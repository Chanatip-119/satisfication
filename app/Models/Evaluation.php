<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $table = 'evaluation';
    protected $primaryKey = 'evaluation_id';
    public $timestamps = false;

    protected $fillable = [
        'rating',
        'comment',
        'checkin_id'
    ];

    // เปิดใช้งานความสัมพันธ์เชื่อมกลับไปยัง Checkin เพื่อแก้ไขปัญหา BadMethodCallException
    public function checkin()
    {
        return $this->belongsTo(Checkin::class, 'checkin_id', 'checkin_id');
    }

    public function getRouteKeyName()
    {
        return 'evaluation_id';
    }

    // ===== [สีคะแนน] เกณฑ์สีคะแนนเฉลี่ยที่ใช้ร่วมกันทุกหน้า =====
    // เดิม: แต่ละหน้าใช้เกณฑ์ของตัวเอง
    //   - หน้าบุคลากร: เขียวทุกค่า
    //   - หน้ารายงาน Staff / รายงานเคาน์เตอร์: 2 ระดับ (≥ 4 เขียว, < 4 เหลือง)
    //   - แดชบอร์ด: 3 ระดับ
    // ใหม่: ทุกหน้าใช้ 3 ระดับเดียวกัน — แก้เกณฑ์ที่นี่ที่เดียว ทุกหน้าเปลี่ยนตาม
    //   ≥ 4.0  เขียว (score-high) | 3.0–3.9  เหลือง (score-mid) | < 3.0  แดง (score-low)
    // คลาส CSS อยู่ใน public/css/layout.css
    public const SCORE_HIGH = 4.0;
    public const SCORE_MID  = 3.0;

    public static function scoreClass($score): string
    {
        $score = (float) $score;

        if ($score >= self::SCORE_HIGH) {
            return 'score-high';
        }
        if ($score >= self::SCORE_MID) {
            return 'score-mid';
        }
        return 'score-low';
    }
    // ===== [จบ สีคะแนน] =====
}