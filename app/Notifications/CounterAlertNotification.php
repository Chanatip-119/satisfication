<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * [Check-in ฟีเจอร์ใหม่ ข้อ 1] แจ้งเตือนหัวหน้าในเว็บ (เก็บในตาราง notifications)
 * ใช้กับ 2 กรณี:
 *   - unattended         : เคาน์เตอร์มีกะอยู่แต่ไม่มีใคร Check-in เกินเวลาที่กำหนด
 *   - extension_overdue  : ต่อเวลาแล้วเลยเวลาที่ต่อไว้ แต่ยังไม่ Check-out
 */
class CounterAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $message,
        public ?int $counterSubId = null,
        public ?int $scheduleId = null,
        public ?string $timeLabel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind'           => $this->kind,
            'title'          => $this->title,
            'message'        => $this->message,
            'counter_sub_id' => $this->counterSubId,
            'schedule_id'    => $this->scheduleId,
            // เก็บเวลาไทยเป็นข้อความไว้เลย เพื่อแสดงตรงกันไม่ว่า timezone ของแอปจะตั้งเป็นอะไร
            'time_label'     => $this->timeLabel,
        ];
    }
}