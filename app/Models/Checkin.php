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
        'is_kicked'
    ];

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

    public function getRouteKeyName()
    {
        return 'checkin_id';
    }

    public function substituteStaff()
    {
        return $this->belongsToMany(Staff::class, 'substitute_has_staff', 'checkin_id', 'staff_id');
    }
}
