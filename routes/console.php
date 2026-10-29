<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// [Check-in ฟีเจอร์ใหม่] ตรวจเคาน์เตอร์ทุกนาที: Check-out อัตโนมัติ + แจ้งเตือนหัวหน้า
// ต้องมีตัวรัน Scheduler (php artisan schedule:work หรือ cron เรียก schedule:run ทุกนาที)
// withoutOverlapping: ถ้ารอบก่อนยังไม่เสร็จ จะไม่เริ่มรอบใหม่ซ้อน
Schedule::command('counters:monitor')->everyMinute()->withoutOverlapping();