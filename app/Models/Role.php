<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'role';
    protected $primaryKey = 'role_id';
    public $timestamps = false;

    protected $fillable = [
        'role_id',
        'role_name'
    ];

    public function getRouteKeyName()
    {
        return 'role_id';
    }

    public function staff()
    {
        return $this->hasMany(Staff::class, 'role_id', 'role_id');
    }
}
