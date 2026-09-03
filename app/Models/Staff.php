<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Hash;

class Staff extends Model
{

    protected $table = 'staff';
    protected $primaryKey = 'staff_id';
    public $timestamps = false;

    protected $fillable = [
        'staff_name',
        'staff_email',
        'staff_pincode',
        'role_id'
    ];
    protected $hidden = [
        'pin_code'
    ];

    public function setPinCodeAttribute($value)
    {
        $this->attributes['pin_code'] = Hash::make($value);
    }

    public function verifyPinCode($value)
    {
        return Hash::check($value, $this->pin_code);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function checkins()
    {
        return $this->hasMany(Checkin::class, 'staff_id', 'staff_id');
    }
}
