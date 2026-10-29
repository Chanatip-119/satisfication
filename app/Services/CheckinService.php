<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Schedule;
use Carbon\Carbon;

/**
 * [Check-in ฟีเจอร์ใหม่] ตรรกะเกี่ยวกับเวลาและการ Check-out อัตโนมัติ — รวมไว้ที่เดียว
 *
 * เดิม: โค้ด Check-out อัตโนมัติถูกเขียนซ้ำ 3 ที่ (CheckinController::index, CheckinController::status,
 *       DashboardController::index) แต่ละที่ต่างกันเล็กน้อย
 * ใหม่: ทุกที่เรียกผ่าน class นี้ และรองรับการต่อเวลา (ข้อ 2) — Check-in ที่ต่อเวลาแล้วจะไม่ถูก Check-out อัตโนมัติ
 *
 * เวลาทั้งหมดใช้ Asia/Bangkok เพราะ checkin_at / checkout_at ถูกบันทึกเป็นเวลาไทย
 */
class CheckinService
{
    public const TZ = 'Asia/Bangkok';

    /** ปุ่มต่อเวลาจะแสดงเมื่อเหลือเวลาไม่เกินกี่นาที (ข้อ 2: 2 ชั่วโมง) */
    public const EXTEND_WINDOW_MINUTES = 120;

    /** ต่อเวลาได้รวมสูงสุดกี่ชั่วโมงหลังเวลาสิ้นสุดกะ (ข้อ 2: 1–2 ชม.) */
    public const MAX_EXTEND_HOURS = 2;

    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }

    /** แปลงค่าเวลาจาก DB (สตริงเวลาไทย) เป็น Carbon */
    public static function parse($value): ?Carbon
    {
        return $value ? Carbon::parse($value, self::TZ) : null;
    }

    public static function scheduleStart(Schedule $schedule): Carbon
    {
        $startStr = date('H:i:s', strtotime($schedule->start_time));
        return Carbon::parse($schedule->schedule_date . ' ' . $startStr, self::TZ);
    }

    public static function scheduleEnd(Schedule $schedule): Carbon
    {
        $endStr = date('H:i:s', strtotime($schedule->end_time));
        return Carbon::parse($schedule->schedule_date . ' ' . $endStr, self::TZ);
    }

    /**
     * เวลาสิ้นสุดที่ใช้จริงของ Check-in นี้: ถ้าต่อเวลาแล้วใช้เวลาที่ต่อ ไม่งั้นใช้เวลาสิ้นสุดกะ
     */
    public static function effectiveEnd(Checkin $checkin): ?Carbon
    {
        if ($checkin->extended_until) {
            return self::parse($checkin->extended_until);
        }
        return $checkin->schedule ? self::scheduleEnd($checkin->schedule) : null;
    }

    /** จำนวนนาทีระหว่าง 2 เวลา (เป็นบวกเสมอ) — กันปัญหาค่าติดลบของ Carbon 3 */
    public static function minutesBetween(Carbon $from, Carbon $to): int
    {
        return (int) round(abs($to->getTimestamp() - $from->getTimestamp()) / 60);
    }

    /**
     * ปิด Check-in 1 รายการ (ใช้ร่วมกันทุกกรณี: เอง / อัตโนมัติ / รับช่วงต่อ / หัวหน้าบังคับ)
     */
    public static function closeCheckin(Checkin $checkin, string $reason, ?Carbon $at = null): void
    {
        $at = $at ?? self::now();

        $checkin->checkout_at = $at;
        $checkin->duration_min = self::minutesBetween(self::parse($checkin->checkin_at) ?? $at, $at);
        // คง is_kicked ไว้ให้โค้ดเดิม/รายงานเดิมยังทำงานได้: 1 = ไม่ได้ Check-out เอง
        $checkin->is_kicked = $reason === Checkin::REASON_MANUAL ? 0 : 1;
        $checkin->checkout_reason = $reason;
        $checkin->save();

        if ($checkin->schedule_id) {
            Schedule::where('schedule_id', $checkin->schedule_id)
                ->where('status', 'checkin')
                ->update(['status' => 'empty']);
        }
    }

    /**
     * Check-out อัตโนมัติให้คนที่หมดเวลากะแล้ว
     * ข้าม Check-in ที่ต่อเวลา (manual_checkout_required) เพราะต้อง Check-out เองเท่านั้น
     */
    public static function autoCheckoutExpired(): int
    {
        $now = self::now();
        $closed = 0;

        $checkins = Checkin::with('schedule')
            ->whereNull('checkout_at')
            ->where(function ($query) {
                $query->whereNull('manual_checkout_required')->orWhere('manual_checkout_required', false);
            })
            ->get();

        foreach ($checkins as $checkin) {
            if (!$checkin->schedule) {
                continue;
            }
            $end = self::scheduleEnd($checkin->schedule);
            if ($now->greaterThanOrEqualTo($end)) {
                // บันทึกเวลา Check-out เป็นเวลาสิ้นสุดกะจริง ไม่ใช่เวลาที่ระบบมาตรวจเจอ
                self::closeCheckin($checkin, Checkin::REASON_AUTO, $end);
                $closed++;
            }
        }

        return $closed;
    }

    /**
     * รีเซ็ตสถานะกะที่หมดเวลาแล้วให้เป็น empty
     * ข้ามกะที่ยังมีคน Check-in อยู่ (เช่น ต่อเวลา) ไม่งั้นการ์ดจะกลายเป็น "สิ้นสุดเวลา" ทั้งที่ยังนั่งอยู่
     */
    public static function clearEndedScheduleStatus(): void
    {
        $now = self::now();
        $activeScheduleIds = Checkin::whereNull('checkout_at')->pluck('schedule_id')->filter()->unique()->all();

        $schedules = Schedule::whereDate('schedule_date', '<=', $now->toDateString())
            ->whereNotIn('status', ['empty', 'close'])
            ->when(!empty($activeScheduleIds), fn ($query) => $query->whereNotIn('schedule_id', $activeScheduleIds))
            ->get();

        foreach ($schedules as $schedule) {
            if ($now->greaterThanOrEqualTo(self::scheduleEnd($schedule))) {
                $schedule->status = 'empty';
                $schedule->save();
            }
        }
    }

    /** เรียกทั้ง 2 อย่าง — ใช้ในหน้าเว็บที่เคยทำงานนี้ไว้ (เป็นตัวสำรอง ถ้า Scheduler ยังไม่ได้ตั้ง) */
    public static function runHousekeeping(): void
    {
        try {
            self::autoCheckoutExpired();
            self::clearEndedScheduleStatus();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * ข้อ 4: Check-in ที่ยังเปิดอยู่ของพนักงานคนนี้ ที่ "จุดอื่น" (ไม่ใช่ $exceptCounterSubId)
     */
    public static function activeCheckinElsewhere(int $staffId, ?int $exceptCounterSubId = null): ?Checkin
    {
        return Checkin::with('schedule')
            ->where('staff_id', $staffId)
            ->whereNull('checkout_at')
            ->whereHas('schedule', function ($query) use ($exceptCounterSubId) {
                if ($exceptCounterSubId) {
                    $query->where('counter_sub_id', '!=', $exceptCounterSubId);
                }
            })
            ->orderByDesc('checkin_at')
            ->first();
    }

    /**
     * ข้อ 2: ข้อมูลการต่อเวลาสำหรับหน้า Check-in ขั้นที่ 4
     * @return object{is_extended: bool, can_extend: bool, options: int[], end_label: string, extended_label: ?string, minutes_left: ?int}
     */
    public static function extensionInfo(Checkin $checkin): object
    {
        $now = self::now();
        $info = (object) [
            'is_extended'    => (bool) $checkin->manual_checkout_required,
            'can_extend'     => false,
            'options'        => [],
            'end_label'      => '-',
            'extended_label' => $checkin->extended_until ? self::parse($checkin->extended_until)->format('H:i') : null,
            'minutes_left'   => null,
        ];

        if (!$checkin->schedule) {
            return $info;
        }

        $scheduleEnd = self::scheduleEnd($checkin->schedule);
        $currentEnd = self::effectiveEnd($checkin);
        $maxEnd = $scheduleEnd->copy()->addHours(self::MAX_EXTEND_HOURS);

        $info->end_label = $currentEnd->format('H:i');
        $info->minutes_left = $now->lessThan($currentEnd) ? self::minutesBetween($now, $currentEnd) : 0;

        // ต่อได้เมื่อเหลือเวลา ≤ 2 ชม. และยังไม่ถึงเพดานรวม 2 ชม. หลังเวลากะ
        $withinWindow = $info->minutes_left <= self::EXTEND_WINDOW_MINUTES;
        foreach ([1, 2] as $hours) {
            if ($currentEnd->copy()->addHours($hours)->lessThanOrEqualTo($maxEnd)) {
                $info->options[] = $hours;
            }
        }
        $info->can_extend = $withinWindow && !empty($info->options);

        return $info;
    }
}