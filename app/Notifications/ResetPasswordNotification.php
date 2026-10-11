<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('รีเซ็ตรหัสผ่านสำหรับระบบประเมินความพึงพอใจ (BUU)')
            ->greeting('สวัสดีครับ/ค่ะ ' . ($notifiable->staff_name ?? 'ผู้ใช้งาน'))
            ->line('เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณในระบบจัดการและประเมินความพึงพอใจ มหาวิทยาลัยบูรพา')
            ->line('คุณสามารถคลิกปุ่มด้านล่างนี้เพื่อตั้งรหัสผ่านใหม่ได้ทันที:')
            ->action('ตั้งรหัสผ่านใหม่', $resetUrl)
            ->line('หมายเหตุ: ลิงก์สำหรับรีเซ็ตรหัสผ่านนี้จะหมดอายุภายใน 60 นาที')
            ->line('หากคุณไม่ได้เป็นผู้ส่งคำขอรีเซ็ตรหัสผ่านนี้ สามารถเพิกเฉยต่ออีเมลนี้ได้อย่างปลอดภัย โดยรหัสผ่านเดิมของคุณจะไม่มีการเปลี่ยนแปลง')
            ->salutation('ขอแสดงความนับถือ, ทีมพัฒนาระบบ BUU');
    }
}
