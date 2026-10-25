<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class Staff extends Authenticatable
{
    public const ROLE_EMPLOYEE = 4;
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

    public function getEmailForPasswordReset()
    {
        return $this->staff_email;
    }

    public function routeNotificationForMail($notification = null)
    {
        return $this->staff_email;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }


    public function isEmployee(): bool
    {
        return (int) $this->role_id === self::ROLE_EMPLOYEE;
    }

    protected $fillable = [
        'staff_name',
        'staff_email',
        'staff_pincode',
        'password',
        'role_id',
        'department',
        'position'
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
