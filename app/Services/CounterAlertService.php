<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\CounterSub;
use App\Models\Schedule;
use App\Models\Staff;
use App\Notifications\CounterAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * [Check-in ฟีเจอร์ใหม่ ข้อ 1] ตรวจสถานะเคาน์เตอร์และส่งแจ้งเตือนหัวหน้า
 * ถูกเรียกทุกนาทีจากคำสั่ง counters:monitor (ดู routes/console.php)
 *
 * ผู้รับแจ้งเตือน: ทุกคนที่ไม่ใช่ Employee (Executive, Super_Admin, Admin)
 * กันแจ้งซ้ำ: เคาน์เตอร์ว่าง 1 ช่วง = แจ้ง 1 ครั้ง (ใช้ Cache::add เป็นตัวล็อก)
 */
class CounterAlertService
{
    /** เคาน์เตอร์ว่างกี่นาทีจึงแจ้งเตือน */
    public const UNATTENDED_MINUTES = 15;

    /** ต่อเวลาแล้วเลยเวลาที่ต่อไว้กี่นาทีจึงแจ้งเตือน */
    public const EXTENSION_OVERDUE_MINUTES = 30;

    /**
     * เคาน์เตอร์ที่มีกะอยู่ แต่ไม่มีใคร Check-in เกิน 15 นาที
     * ครอบคลุม 2 กรณี:
     *   1) คนก่อนหน้า Check-out ไปแล้ว คนต่อไปไม่ Check-in
     *   2) ตั้งแต่เริ่มกะยังไม่มีใคร Check-in (เช่น มานั่งแต่ไม่ยอม Check-in)
     */
    public static function checkUnattended(): int
    {
        $now = CheckinService::now();
        $today = $now->toDateString();
        $sent = 0;

        // จุดย่อยที่มีคน Check-in อยู่ตอนนี้
        $occupiedSubIds = DB::table('checkin')
            ->join('schedule', 'schedule.schedule_id', '=', 'checkin.schedule_id')
            ->whereNull('checkin.checkout_at')
            ->pluck('schedule.counter_sub_id')
            ->filter()
            ->unique()
            ->all();

        $schedules = Schedule::with('staff')
            ->where('schedule_date', $today)
            ->whereNotNull('staff_id')
            ->whereNotNull('counter_sub_id')
            ->where('status', '!=', 'close')
            // [รายงาน+Online ข้อ 4] ไม่ตรวจเคาน์เตอร์ Online (ไม่มีคนนั่งโดยตั้งใจ จึงไม่ใช่ "เคาน์เตอร์ว่าง")
            ->whereHas('counterSub.counter', fn ($q) => $q->where('is_online', false))
            ->orderBy('start_time')
            ->get();

        foreach ($schedules as $schedule) {
            $start = CheckinService::scheduleStart($schedule);
            $end = CheckinService::scheduleEnd($schedule);

            // อยู่นอกช่วงกะ หรือจุดนี้มีคนนั่งอยู่แล้ว → ไม่ต้องแจ้ง
            if ($now->lessThan($start) || $now->greaterThanOrEqualTo($end)) {
                continue;
            }
            if (in_array($schedule->counter_sub_id, $occupiedSubIds)) {
                continue;
            }

            // คนล่าสุดที่ Check-out จากจุดนี้ ภายในช่วงกะนี้
            $lastCheckout = Checkin::with('staff')
                ->whereNotNull('checkout_at')
                ->where('checkout_at', '>=', $start->format('Y-m-d H:i:s'))
                ->whereHas('schedule', fn ($q) => $q->where('counter_sub_id', $schedule->counter_sub_id))
                ->orderByDesc('checkout_at')
                ->first();

            $emptySince = $lastCheckout ? CheckinService::parse($lastCheckout->checkout_at) : $start;
            if ($emptySince->lessThan($start)) {
                $emptySince = $start;
            }

            $emptyMinutes = CheckinService::minutesBetween($emptySince, $now);
            if ($emptyMinutes < self::UNATTENDED_MINUTES) {
                continue;
            }

            // กันแจ้งซ้ำ: จุดเดียวกัน + เริ่มว่างเวลาเดียวกัน = ช่วงว่างเดียวกัน แจ้งครั้งเดียว
            $lockKey = 'counter-unattended:' . $schedule->counter_sub_id . ':' . $emptySince->timestamp;
            if (!Cache::add($lockKey, true, now()->addDay())) {
                continue;
            }

            $counterLabel = CounterSub::labelFor($schedule->counter_sub_id);
            $staffName = $schedule->staff->staff_name ?? 'ไม่ระบุ';
            $shiftRange = substr($schedule->start_time, 0, 5) . '–' . substr($schedule->end_time, 0, 5);

            $message = "ไม่มีผู้ Check-in มา {$emptyMinutes} นาที (ตั้งแต่ {$emptySince->format('H:i')} น.) — กะของ {$staffName} เวลา {$shiftRange} น.";
            $message .= $lastCheckout
                ? " / ผู้ปฏิบัติงานก่อนหน้า " . ($lastCheckout->staff->staff_name ?? 'ไม่ระบุ') . " Check-out เมื่อ " . CheckinService::parse($lastCheckout->checkout_at)->format('H:i') . " น."
                : " / ยังไม่มีใคร Check-in ตั้งแต่เริ่มกะ";

            self::notifySupervisors(new CounterAlertNotification(
                'unattended',
                "{$counterLabel} ไม่มีผู้ Check-in",
                $message,
                (int) $schedule->counter_sub_id,
                (int) $schedule->schedule_id,
                $now->format('j/n H:i') . ' น.'
            ));
            $sent++;
        }

        return $sent;
    }

    /**
     * ข้อ 2 (ส่วนเสริม): ต่อเวลาแล้ว เลยเวลาที่ต่อไว้เกิน 30 นาที แต่ยังไม่ Check-out
     * เพราะระบบจะไม่ Check-out ให้อัตโนมัติ ถ้าลืม Check-out จะค้างไปเรื่อย ๆ
     */
    public static function checkOverdueExtensions(): int
    {
        $now = CheckinService::now();
        $sent = 0;

        $checkins = Checkin::with(['staff', 'schedule'])
            ->whereNull('checkout_at')
            ->where('manual_checkout_required', true)
            ->whereNotNull('extended_until')
            ->get();

        foreach ($checkins as $checkin) {
            $extendedUntil = CheckinService::parse($checkin->extended_until);
            if ($now->lessThan($extendedUntil->copy()->addMinutes(self::EXTENSION_OVERDUE_MINUTES))) {
                continue;
            }

            if (!Cache::add('extension-overdue:' . $checkin->checkin_id, true, now()->addDays(2))) {
                continue;
            }

            $counterSubId = $checkin->schedule->counter_sub_id ?? null;
            $counterLabel = $counterSubId ? CounterSub::labelFor($counterSubId) : 'เคาน์เตอร์';
            $staffName = $checkin->staff->staff_name ?? 'ไม่ระบุ';

            self::notifySupervisors(new CounterAlertNotification(
                'extension_overdue',
                "{$counterLabel} ต่อเวลาเกินกำหนดแล้วยังไม่ Check-out",
                "{$staffName} ต่อเวลาถึง {$extendedUntil->format('H:i')} น. แต่ยังไม่ Check-out — หากไม่ได้อยู่ที่เคาน์เตอร์แล้ว สามารถกด \"บังคับ Check-out\" ได้ในหน้าตารางปฏิบัติงาน",
                $counterSubId ? (int) $counterSubId : null,
                $checkin->schedule_id ? (int) $checkin->schedule_id : null,
                $now->format('j/n H:i') . ' น.'
            ));
            $sent++;
        }

        return $sent;
    }

    /** ส่งให้ทุกคนที่ไม่ใช่ Employee (Executive, Super_Admin, Admin) */
    protected static function notifySupervisors(CounterAlertNotification $notification): void
    {
        $recipients = Staff::where('role_id', '!=', Staff::ROLE_EMPLOYEE)->get();
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }
}