<?php

namespace App\Console\Commands;

use App\Services\CheckinService;
use App\Services\CounterAlertService;
use Illuminate\Console\Command;

/**
 * [Check-in ฟีเจอร์ใหม่] งานที่ต้องทำทุกนาที แม้ไม่มีใครเปิดหน้าเว็บ
 *   1. Check-out อัตโนมัติให้คนที่หมดเวลากะ (ยกเว้นคนที่ต่อเวลา)
 *   2. รีเซ็ตสถานะกะที่หมดเวลา
 *   3. แจ้งเตือนหัวหน้าเมื่อเคาน์เตอร์ว่างเกิน 15 นาที (ข้อ 1)
 *   4. แจ้งเตือนหัวหน้าเมื่อต่อเวลาแล้วเลยกำหนดเกิน 30 นาที
 *
 * ทดสอบเองได้ด้วย: php artisan counters:monitor
 */
class MonitorCounters extends Command
{
    protected $signature = 'counters:monitor';

    protected $description = 'Check-out อัตโนมัติ และแจ้งเตือนหัวหน้าเมื่อเคาน์เตอร์ไม่มีผู้ Check-in';

    public function handle(): int
    {
        $closed = CheckinService::autoCheckoutExpired();
        CheckinService::clearEndedScheduleStatus();
        $unattended = CounterAlertService::checkUnattended();
        $overdue = CounterAlertService::checkOverdueExtensions();

        $this->info("Check-out อัตโนมัติ {$closed} รายการ | แจ้งเตือนเคาน์เตอร์ว่าง {$unattended} | แจ้งเตือนต่อเวลาเกินกำหนด {$overdue}");

        return self::SUCCESS;
    }
}