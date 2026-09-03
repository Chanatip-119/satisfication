<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedule';
    protected $primaryKey = 'schedule_id';
    public $timestamps = false;

    protected $fillable = [
        'schedule_id',
        'staff_id', 
        'counter_sub_id',
        'schedule_date',
        'start_time',
        'end_time',
        'status'
    ];

    public function checkins()
    {
        return $this->hasMany(Checkin::class, 'schedule_id', 'schedule_id');
    }

    public function counterSub()
    {
        return $this->belongsTo(CounterSub::class, 'counter_sub_id', 'counter_sub_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function getRouteKeyName()
    {
        return 'schedule_id';
    }
}