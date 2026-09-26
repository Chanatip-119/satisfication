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
}