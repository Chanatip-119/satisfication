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
    ];

    public function countersubs()
    {
        return $this->hasMany(CounterSub::class, 'counter_id', 'counter_id');
    }

    public function getRouteKeyName()
    {
        return 'counter_id';
    }
}
