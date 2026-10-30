<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Evaluation;
use Carbon\Carbon;

/**
 * [รายงาน+Online ข้อ 2/3] รวบรวมข้อมูลรายงานการประเมิน — ใช้ร่วมกันทั้ง PDF และ Excel
 *
 * คำนวณครั้งเดียวที่นี่ ตัวเลขใน PDF และ Excel จึงตรงกันเสมอ
 *
 * หลักการคำนวณ:
 * - "คะแนนเฉลี่ย" = ค่าเฉลี่ยของคะแนนทุกการประเมิน (ถ่วงตามจำนวน ไม่ใช่เฉลี่ยของค่าเฉลี่ย)
 * - คอลัมน์ "เคาน์เตอร์" = เคาน์เตอร์หลัก (เช่น 8) รวมทุกจุดย่อย (8.1, 8.2 …)
 * - การประเมินจากเคาน์เตอร์ Online ไม่มีบุคลากร จึงแยกเป็นแถว "ประเมิน Online" ในตารางรายบุคลากร
 * - การประเมินหาเคาน์เตอร์จาก counter_sub_id (ข้อมูลเก่าที่ยังว่าง ใช้ checkin → schedule แทน)
 */
class EvaluationReportService
{
    /** @param int[] $counterIds เคาน์เตอร์หลักที่เลือก (ว่าง = ทุกเคาน์เตอร์) */
    public function __construct(
        protected Carbon $start,
        protected Carbon $end,
        protected array $counterIds = [],
    ) {}

    public function build(): array
    {
        $counters = $this->loadCounters();
        $subMap = $this->buildSubMap($counters);
        $evaluations = $this->loadEvaluations($subMap);
        $sessions = $this->loadSessions($subMap, $evaluations);

        return [
            'range'         => ['start' => $this->start, 'end' => $this->end],
            'generated_at'  => CheckinService::now(),
            'counters'      => $this->counterColumns($counters),
            'counter_details' => $this->counterDetails($counters, $evaluations, $sessions),
            'by_staff'      => $this->pivotByStaff($counters, $evaluations),
            'by_date'       => $this->pivotByDate($counters, $evaluations),
            'sessions'      => $sessions,
            'evaluations'   => $evaluations,
            'distribution'  => $this->distribution($evaluations),
            'total'         => $this->summarize($evaluations),
        ];
    }

    // ------------------------------------------------------------------
    // โหลดข้อมูล
    // ------------------------------------------------------------------

    protected function loadCounters()
    {
        return Counter::with('countersubs')
            ->when(!empty($this->counterIds), fn ($q) => $q->whereIn('counter_id', $this->counterIds))
            ->orderBy('counter_id')
            ->get();
    }

    /** counter_sub_id => ข้อมูลจุดย่อย (เฉพาะเคาน์เตอร์ที่อยู่ในรายงาน) */
    protected function buildSubMap($counters): array
    {
        $map = [];
        foreach ($counters as $counter) {
            foreach ($counter->countersubs as $sub) {
                $map[(int) $sub->counter_sub_id] = [
                    'sub_id'     => (int) $sub->counter_sub_id,
                    'code'       => CounterSub::codeFor($sub->counter_sub_id) ?? ($counter->counter_id . '.?'),
                    'counter_id' => (int) $counter->counter_id,
                    'location'   => (string) $counter->counter_location,
                    'is_online'  => (bool) $counter->is_online,
                ];
            }
        }
        return $map;
    }

    protected function loadEvaluations(array $subMap): array
    {
        $rows = [];

        $evaluations = Evaluation::with(['checkin.staff', 'checkin.schedule'])
            ->whereBetween('evaluation_at', [$this->start->format('Y-m-d H:i:s'), $this->end->format('Y-m-d H:i:s')])
            ->orderBy('evaluation_at')
            ->orderBy('evaluation_id')
            ->get();

        foreach ($evaluations as $evaluation) {
            $subId = $evaluation->counter_sub_id
                ?? optional(optional($evaluation->checkin)->schedule)->counter_sub_id;
            $subId = $subId !== null ? (int) $subId : null;

            // ไม่อยู่ในเคาน์เตอร์ที่เลือก (หรือหาเคาน์เตอร์ไม่ได้) → ไม่นับ
            if ($subId === null || !isset($subMap[$subId])) {
                continue;
            }

            $sub = $subMap[$subId];
            $at = CheckinService::parse($evaluation->evaluation_at);
            $staff = optional($evaluation->checkin)->staff;

            $rows[] = [
                'id'         => (int) $evaluation->evaluation_id,
                'at'         => $at,
                'date'       => $at->format('Y-m-d'),
                'date_label' => $at->format('d/m/') . ($at->year + 543),
                'time'       => $at->format('H:i'),
                'sub_id'     => $subId,
                'code'       => $sub['code'],
                'counter_id' => $sub['counter_id'],
                'location'   => $sub['location'],
                'is_online'  => $sub['is_online'],
                'checkin_id' => $evaluation->checkin_id ? (int) $evaluation->checkin_id : null,
                'staff_id'   => $staff ? (int) $staff->staff_id : null,
                'staff_name' => $staff ? $staff->staff_name : ($sub['is_online'] ? 'ประเมิน Online' : 'ไม่ระบุ'),
                'rating'     => (int) $evaluation->rating, // ใน DB เป็น ENUM ข้อความ '1'..'5'
                'comment'    => trim((string) $evaluation->comment),
                'note'       => trim((string) $evaluation->staff_note),
                'note_at'    => $evaluation->staff_note_at ? self::thaiDateTime(CheckinService::parse($evaluation->staff_note_at)) : '',
            ];
        }

        return $rows;
    }

    /** รอบการ Check-in (เวลาเริ่ม–สิ้นสุดที่นั่งจริง) ที่เริ่มในช่วงเวลาของรายงาน */
    protected function loadSessions(array $subMap, array $evaluations): array
    {
        $statsByCheckin = [];
        foreach ($evaluations as $row) {
            if ($row['checkin_id']) {
                $statsByCheckin[$row['checkin_id']][] = $row['rating'];
            }
        }

        $reasonLabels = [
            Checkin::REASON_MANUAL   => 'Check-out เอง',
            Checkin::REASON_AUTO     => 'อัตโนมัติ (หมดเวลากะ)',
            Checkin::REASON_TAKEOVER => 'ถูกรับช่วงต่อ',
            Checkin::REASON_FORCED   => 'หัวหน้าบังคับ Check-out',
        ];

        $now = CheckinService::now();
        $rows = [];

        $checkins = Checkin::with(['staff', 'schedule', 'substitutingFor'])
            ->whereBetween('checkin_at', [$this->start->format('Y-m-d H:i:s'), $this->end->format('Y-m-d H:i:s')])
            ->orderBy('checkin_at')
            ->get();

        foreach ($checkins as $checkin) {
            $subId = optional($checkin->schedule)->counter_sub_id;
            if ($subId === null || !isset($subMap[(int) $subId])) {
                continue;
            }
            $sub = $subMap[(int) $subId];

            $in = CheckinService::parse($checkin->checkin_at);
            $out = CheckinService::parse($checkin->checkout_at);
            $minutes = CheckinService::minutesBetween($in, $out ?? $now);

            // ข้อมูลเก่าก่อนมี checkout_reason: เดาจาก is_kicked
            $reason = $checkin->checkout_reason
                ?? ($out ? ($checkin->is_kicked ? Checkin::REASON_AUTO : Checkin::REASON_MANUAL) : null);

            $ratings = $statsByCheckin[(int) $checkin->checkin_id] ?? [];

            $rows[] = [
                'checkin_id'   => (int) $checkin->checkin_id,
                'date'         => $in->format('Y-m-d'),
                'date_label'   => $in->format('d/m/') . ($in->year + 543),
                'staff_name'   => optional($checkin->staff)->staff_name ?? 'ไม่ระบุ',
                'code'         => $sub['code'],
                'counter_id'   => $sub['counter_id'],
                'location'     => $sub['location'],
                'shift'        => $checkin->schedule
                    ? substr($checkin->schedule->start_time, 0, 5) . '–' . substr($checkin->schedule->end_time, 0, 5)
                    : '-',
                'in'           => $in->format('H:i'),
                // [แก้ PDF] ถ้า Check-out คนละวันกับ Check-in ให้ใส่วันที่ด้วย
                // เดิม: แสดงแค่เวลา เช่น เข้า 19:11 ออก 18:40 (ระยะเวลา 23 ชม.) ดูเหมือนออกก่อนเข้า
                'out'          => $out
                    ? ($out->isSameDay($in) ? $out->format('H:i') : $out->format('j/n') . ' ' . $out->format('H:i'))
                    : 'ยังนั่งอยู่',
                'minutes'      => $minutes,
                'duration'     => self::durationLabel($minutes),
                'reason'       => $out ? ($reasonLabels[$reason] ?? 'Check-out') : '-',
                'takeover_from'=> optional($checkin->substitutingFor)->staff_name,
                'extended_until' => $checkin->extended_until ? CheckinService::parse($checkin->extended_until)->format('H:i') : '',
                'eval_count'   => count($ratings),
                'average'      => $ratings ? round(array_sum($ratings) / count($ratings), 2) : null,
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // ตารางสรุป
    // ------------------------------------------------------------------

    /** หัวคอลัมน์เคาน์เตอร์ (เรียงตามเลขเคาน์เตอร์) */
    protected function counterColumns($counters): array
    {
        return $counters->map(fn ($c) => [
            'counter_id' => (int) $c->counter_id,
            'label'      => 'เคาน์เตอร์ ' . $c->counter_id,
            'location'   => (string) $c->counter_location,
            'is_online'  => (bool) $c->is_online,
        ])->values()->all();
    }

    /** ตารางสรุป: แถว = บุคลากร, คอลัมน์ = เคาน์เตอร์ + คะแนนรวม */
    protected function pivotByStaff($counters, array $evaluations): array
    {
        $groups = [];
        foreach ($evaluations as $row) {
            $key = $row['staff_id'] !== null ? 'staff-' . $row['staff_id'] : ($row['is_online'] ? 'online' : 'unknown');
            $groups[$key]['label'] = $row['staff_name'];
            $groups[$key]['sort'] = $key === 'online' ? 'zzz-1' : ($key === 'unknown' ? 'zzz-2' : $row['staff_name']);
            $groups[$key]['rows'][] = $row;
        }

        uasort($groups, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->pivotRow($group['label'], $group['rows'], $counters);
        }

        return [
            'rows'  => $result,
            'total' => $this->pivotRow('รวมทั้งหมด', $evaluations, $counters),
        ];
    }

    /** ตารางสรุป: แถว = วัน, คอลัมน์ = เคาน์เตอร์ + คะแนนรวม (เฉพาะวันที่มีการประเมิน) */
    protected function pivotByDate($counters, array $evaluations): array
    {
        $groups = [];
        foreach ($evaluations as $row) {
            $groups[$row['date']]['label'] = $row['date_label'];
            $groups[$row['date']]['rows'][] = $row;
        }
        ksort($groups);

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->pivotRow($group['label'], $group['rows'], $counters);
        }

        return [
            'rows'  => $result,
            'total' => $this->pivotRow('รวมทั้งหมด', $evaluations, $counters),
        ];
    }

    /** 1 แถวของตารางสรุป: ค่าเฉลี่ย + จำนวน แยกตามเคาน์เตอร์ และคะแนนรวมของแถว */
    protected function pivotRow(string $label, array $rows, $counters): array
    {
        $cells = [];
        foreach ($counters as $counter) {
            $subset = array_values(array_filter($rows, fn ($r) => $r['counter_id'] === (int) $counter->counter_id));
            $cells[(int) $counter->counter_id] = $this->summarize($subset);
        }

        return [
            'label' => $label,
            'cells' => $cells,
            'total' => $this->summarize($rows),
        ];
    }

    /** รายละเอียดของแต่ละเคาน์เตอร์ในช่วงเวลารายงาน */
    protected function counterDetails($counters, array $evaluations, array $sessions): array
    {
        $details = [];
        foreach ($counters as $counter) {
            $id = (int) $counter->counter_id;
            $counterEvals = array_values(array_filter($evaluations, fn ($r) => $r['counter_id'] === $id));
            $counterSessions = array_values(array_filter($sessions, fn ($s) => $s['counter_id'] === $id));
            $serviceMinutes = array_sum(array_column($counterSessions, 'minutes'));

            $details[] = [
                'counter_id'      => $id,
                'label'           => 'เคาน์เตอร์ ' . $id,
                'location'        => (string) $counter->counter_location,
                'type'            => $counter->is_online ? 'Online (ไม่มีคน Check-in)' : 'ปกติ (มีบุคลากรประจำ)',
                'is_online'       => (bool) $counter->is_online,
                'status'          => $counter->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน',
                'sub_codes'       => $counter->countersubs
                    ->map(fn ($s) => CounterSub::codeFor($s->counter_sub_id) . ($s->is_active ? '' : ' (ปิด)'))
                    ->implode(', ') ?: '-',
                'session_count'   => count($counterSessions),
                'staff_count'     => count(array_unique(array_column($counterSessions, 'staff_name'))),
                'service_minutes' => $serviceMinutes,
                'service_label'   => $counter->is_online ? 'ตลอด 24 ชม.' : self::durationLabel($serviceMinutes),
                'summary'         => $this->summarize($counterEvals),
                'distribution'    => $this->distribution($counterEvals),
                'comment_count'   => count(array_filter($counterEvals, fn ($r) => $r['comment'] !== '')),
                'note_count'      => count(array_filter($counterEvals, fn ($r) => $r['note'] !== '')),
                'evaluations'     => $counterEvals,
                'sessions'        => $counterSessions,
            ];
        }
        return $details;
    }

    // ------------------------------------------------------------------
    // ตัวช่วย
    // ------------------------------------------------------------------

    /** @return array{count: int, average: float|null} */
    protected function summarize(array $rows): array
    {
        $count = count($rows);
        return [
            'count'   => $count,
            'average' => $count > 0 ? round(array_sum(array_column($rows, 'rating')) / $count, 2) : null,
        ];
    }

    /** จำนวนการประเมินแยกตามคะแนน 5 → 1 */
    protected function distribution(array $rows): array
    {
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $row) {
            if (isset($dist[$row['rating']])) {
                $dist[$row['rating']]++;
            }
        }
        return $dist;
    }

    /** วันที่ + เวลา แบบ พ.ศ. เช่น 31/10/2569 14:05 */
    public static function thaiDateTime(Carbon $at): string
    {
        return $at->format('d/m/') . ($at->year + 543) . ' ' . $at->format('H:i');
    }

    public static function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($hours === 0) {
            return "{$mins} นาที";
        }
        return $mins === 0 ? "{$hours} ชม." : "{$hours} ชม. {$mins} นาที";
    }
}