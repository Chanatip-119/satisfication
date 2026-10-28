<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// [แก้ไข ระดับ 1 ข้อ 3] นำ use Illuminate\Support\Facades\Hash ออก เพราะ verifyPinCode() ไม่ได้ใช้ Hash แล้ว

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

    // ===== [แก้ไข ระดับ 1 ข้อ 3] ตรวจ PIN แบบข้อความธรรมดา =====
    // เดิม: return Hash::check($value, $this->staff_pincode);
    // ใช้ไม่ได้จริง เพราะ PIN เก็บเป็นข้อความธรรมดา ไม่ได้ hash
    // เปลี่ยนเป็น hash_equals เพื่อเทียบค่าแบบใช้เวลาคงที่ (กันการวัดเวลาเพื่อเดา PIN)
    public function verifyPinCode($value): bool
    {
        return hash_equals(trim((string) $this->staff_pincode), (string) $value);
    }
    // ===== [จบ ข้อ 3: verifyPinCode] =====

    // ===== [เพิ่มใหม่ ระดับ 1 ข้อ 3] ตัวช่วยจัดการ PIN ไม่ให้ซ้ำกัน =====
    /**
     * สุ่ม PIN 4 หลักที่ไม่ซ้ำกับบุคลากรคนใดในระบบ
     */
    public static function generateUniquePin(): string
    {
        $usedPins = static::pluck('staff_pincode')->map(fn ($pin) => (string) $pin)->flip();

        if ($usedPins->count() >= 10000) {
            throw new \RuntimeException('PIN 4 หลักถูกใช้ครบทุกค่าแล้ว');
        }

        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($usedPins->has($pin));

        return $pin;
    }

    /**
     * ตรวจว่า PIN นี้มีบุคลากรคนอื่น (ที่ไม่ใช่ $exceptStaffId) ใช้อยู่หรือไม่
     */
    public static function pinTakenByOther(string $pin, $exceptStaffId = null): bool
    {
        return static::where('staff_pincode', $pin)
            ->when($exceptStaffId, fn ($query) => $query->where('staff_id', '!=', $exceptStaffId))
            ->exists();
    }
    // ===== [จบ ข้อ 3: ตัวช่วยจัดการ PIN] =====

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function checkins()
    {
        return $this->hasMany(Checkin::class, 'staff_id', 'staff_id');
    }
}