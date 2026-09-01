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
        return 'counter_id';
    }
}
