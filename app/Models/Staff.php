<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Staff extends Authenticatable
{
    use Notifiable;

    protected $table = 'staff';
    protected $primaryKey = 'staff_id';
    public $timestamps = false;

    // Tell Laravel to use staff_email for auth if needed
    public function getAuthIdentifierName()
    {
        return 'staff_id';
    }

    public function getAuthPassword()
    {
        return $this->password;
    }


    protected $fillable = [
        'staff_name',
        'staff_email',
        'staff_pincode',
        'password',
        'role_id'
    ];
    protected $hidden = [
        'password'
    ];

    // pincode ไม่ต้อง hash เพราะเป็นรหัส PIN สำหรับ check-in

    public function verifyPinCode($value)
    {
        return Hash::check($value, $this->staff_pincode);
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
