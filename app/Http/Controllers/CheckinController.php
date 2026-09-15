<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckinController extends Controller
{
    private $libraryName = 'สำนักหอสมุด มหาวิทยาลัย';

    public function index(Request $request)
    {
        if ($request->has('counter_id')) {
            return view('checkin.index', [
                'step' => 2,
                'library_name' => $this->libraryName,
                'selected_counter_id' => $request->counter_id
            ]);
        }

        $countersList = collect();
        $counters = Counter::with('countersubs')->where('is_active', true)->get();

        foreach ($counters as $counter) {
            foreach ($counter->countersubs as $sub) {
                // ดึง Schedule ทั้งหมดของเคาน์เตอร์นี้ในวันนี้
                $schedules = Schedule::where('counter_sub_id', $sub->counter_sub_id)
                    ->where('schedule_date', now()->toDateString())
                    ->get();

                $scheduleIds = $schedules->pluck('schedule_id');
                $hasActiveCheckin = false;
                
                // เช็คว่าเคาน์เตอร์มีคนใช้อยู่ไหม โดยดูจากทุก Schedule ของเคาน์เตอร์นี้
                if ($scheduleIds->isNotEmpty()) {
                    $hasActiveCheckin = Checkin::whereIn('schedule_id', $scheduleIds)
                        ->whereNull('checkout_at')
                        ->exists();
                }

                $firstSchedule = $schedules->first();

                if ($schedules->isNotEmpty() || true) { // เปลี่ยนเป็น true ถ้าอยากให้แสดงเคาน์เตอร์ตลอดแม้ไม่มี Schedule
                    $countersList->push((object)[
                        'id'           => $sub->counter_sub_id,
                        'name'         => 'เคาน์เตอร์ ' . $counter->counter_id . '.' . $sub->counter_sub_id,
                        'time_start'   => $firstSchedule ? substr($firstSchedule->start_time, 0, 5) : '-',
                        'time_end'     => $firstSchedule ? substr($firstSchedule->end_time, 0, 5) : '-',
                        'is_available' => !$hasActiveCheckin,
                    ]);
                }
            }
        }

        return view('checkin.index', [
            'step'         => 1,
            'library_name' => $this->libraryName,
            'counters'     => $countersList
        ]);
    }

    public function step1(Request $request)
    {
        $request->validate(['counter_id' => 'required']);
        return redirect()->route('checkin.index', ['counter_id' => $request->counter_id]);
    }

    public function step2(Request $request)
    {
        $request->validate([
            'counter_id' => 'required',
            'pin'        => 'required|size:4'
        ]);

        $staff = Staff::where('staff_pincode', $request->pin)->first();

        if (!$staff) {
            return back()->withErrors(['pin' => 'PIN ไม่ถูกต้อง']);
        }

        // หา Schedule เฉพาะของ "พนักงานคนนี้"
        $schedule = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('staff_id', $staff->staff_id)
            ->where('schedule_date', now()->toDateString())
            ->first();

        // เช็คหาคนที่กำลังใช้อยู่ปัจจุบัน (จาก schedule_id ไหนก็ได้ของเคาน์เตอร์นี้)
        $allSchedulesToday = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('schedule_date', now()->toDateString())
            ->pluck('schedule_id');

        $activeCheckin = null;
        if ($allSchedulesToday->isNotEmpty()) {
            $activeCheckin = Checkin::whereIn('schedule_id', $allSchedulesToday)
                ->whereNull('checkout_at')
                ->first();
        }

        $currentStaffName = null;
        if ($activeCheckin) {
            $oldStaff = Staff::where('staff_id', $activeCheckin->staff_id)->first();
            $currentStaffName = $oldStaff ? $oldStaff->staff_name : 'บุคลากรท่านอื่น';
        }

        $counterSub = CounterSub::with('counter')->where('counter_sub_id', $request->counter_id)->first();
        $counterName = $counterSub ? 'เคาน์เตอร์ ' . $counterSub->counter_id . '.' . $counterSub->counter_sub_id : 'เคาน์เตอร์ ' . $request->counter_id;

        $counterObj = (object)[
            'id'                 => $request->counter_id,
            'name'               => $counterName,
            'time_start'         => $schedule ? substr($schedule->start_time, 0, 5) : '-',
            'time_end'           => $schedule ? substr($schedule->end_time, 0, 5) : '-',
            'current_staff_name' => $currentStaffName
        ];

        $staffObj = (object)[
            'id'   => $staff->staff_id,
            'name' => $staff->staff_name,
            'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT)
        ];

        return view('checkin.index', [
            'step'         => 3,
            'library_name' => $this->libraryName,
            'counter'      => $counterObj,
            'staff'        => $staffObj
        ]);
    }

    public function step3(Request $request)
    {
        $request->validate([
            'counter_id' => 'required',
            'staff_id'   => 'required'
        ]);

        // ดึง Schedule ของพนักงานคนนี้
        $schedule = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('staff_id', $request->staff_id)
            ->where('schedule_date', now()->toDateString())
            ->first();

        // Auto-Kick บุคลากรเดิมที่ค้างอยู่ + สร้าง Checkin ใหม่ (atomic operation)
        $newCheckin = DB::transaction(function () use ($request, $schedule) {
            $allSchedulesToday = Schedule::where('counter_sub_id', $request->counter_id)
                ->where('schedule_date', now()->toDateString())
                ->pluck('schedule_id');

            if ($allSchedulesToday->isNotEmpty()) {
                $activeCheckin = Checkin::whereIn('schedule_id', $allSchedulesToday)
                    ->whereNull('checkout_at')
                    ->first();

                if ($activeCheckin) {
                    $activeCheckin->checkout_at = now();
                    $activeCheckin->duration_min = now()->diffInMinutes(Carbon::parse($activeCheckin->checkin_at));
                    $activeCheckin->is_kicked = 1;
                    $activeCheckin->save();
                }
            }

            $checkin = new Checkin();
            $checkin->schedule_id = $schedule ? $schedule->schedule_id : null;
            $checkin->staff_id = $request->staff_id;
            $checkin->checkin_at = now();
            $checkin->is_substitute = 0;
            $checkin->is_kicked = 0;
            $checkin->save();

            return $checkin;
        });

        $staff = Staff::where('staff_id', $request->staff_id)->first();
        $counterSub = CounterSub::with('counter')->where('counter_sub_id', $request->counter_id)->first();
        $counterName = $counterSub ? 'เคาน์เตอร์ ' . $counterSub->counter_id . '.' . $counterSub->counter_sub_id : 'เคาน์เตอร์ ' . $request->counter_id;
        $locationName = ($counterSub && $counterSub->counter) ? $counterSub->counter->counter_location : 'ไม่ระบุตำแหน่ง';

        $counterObj = (object)[
            'name'       => $counterName,
            'location'   => $locationName,
            'time_end'   => $schedule ? substr($schedule->end_time, 0, 5) : '-',
        ];

        $staffObj = (object)[
            'name' => $staff ? $staff->staff_name : '-',
        ];

        $showWarningBar = false;
        if ($schedule) {
            $timeEnd = Carbon::parse($schedule->end_time);
            $showWarningBar = now()->diffInMinutes($timeEnd, false) <= 15 && now()->isBefore($timeEnd);
        }

        $evaluations = collect([]);
        if (class_exists(Evaluation::class)) {
            $evaluations = Evaluation::whereIn('checkin_id', function($query) use ($staff) {
                if ($staff) {
                    $query->select('checkin_id')->from('checkin')->where('staff_id', $staff->staff_id);
                }
            })->get();
        }

        $reviewStats = (object)[
            'average' => $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '0.0',
            'total'   => $evaluations->count(),
            'negative'=> $evaluations->where('rating', '<=', 2)->count(),
            'current_counter_total' => $evaluations->count(),
            'current_counter_average' => $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '0.0',
        ];

        $reviewsList = $evaluations->map(function($eval) {
            $dateSource = $eval->created_at ?? now(); 
            return (object)[
                'date'    => Carbon::parse($dateSource)->translatedFormat('j M.'),
                'time'    => Carbon::parse($dateSource)->format('H:i'),
                'rating'  => $eval->rating,
                'comment' => $eval->comment
            ];
        });

        return view('checkin.index', [
            'step'               => 4,
            'library_name'       => $this->libraryName,
            'counter'            => $counterObj,
            'staff'              => $staffObj,
            'checkin_time'       => now()->format('H:i'),
            'current_checkin_id' => $newCheckin->checkin_id,
            'show_warning_bar'   => $showWarningBar,
            'review_stats'       => $reviewStats,
            'review_filters'     => collect([]),
            'reviews'            => $reviewsList
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate(['checkin_id' => 'required']);

        $checkin = Checkin::where('checkin_id', $request->checkin_id)->first();
        if ($checkin) {
            $checkin->checkout_at = now();
            $checkin->duration_min = now()->diffInMinutes(Carbon::parse($checkin->checkin_at));
            $checkin->is_kicked = 0;
            $checkin->save();
        }

        return redirect()->route('checkin.index');
    }
}