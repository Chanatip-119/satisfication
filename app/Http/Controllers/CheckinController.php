<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CheckinController extends Controller
{
    private $libraryName = 'สำนักหอสมุดมหาวิทยาลัยบูรพา';

    public function index(Request $request)
    {
        // สแกน QR Code แล้วข้ามไปหน้ากรอก PIN
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
                $schedule = Schedule::where('counter_sub_id', $sub->counter_sub_id)
                    ->where('schedule_date', now()->toDateString())
                    ->first();

                if ($schedule) {
                    $hasActiveCheckin = Checkin::where('schedule_id', $schedule->schedule_id)
                        ->whereNull('checkout_at')
                        ->exists();

                    $countersList->push((object)[
                        'id'           => $sub->counter_sub_id,
                        'name'         => 'เคาน์เตอร์ ' . $counter->counter_id . '.' . $sub->counter_sub_id,
                        'time_start'   => substr($schedule->start_time, 0, 5),
                        'time_end'     => substr($schedule->end_time, 0, 5),
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

        $schedule = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('schedule_date', now()->toDateString())
            ->first();

        $activeCheckin = null;
        if ($schedule) {
            $activeCheckin = Checkin::where('schedule_id', $schedule->schedule_id)
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

        $schedule = Schedule::where('counter_sub_id', $request->counter_id)
            ->where('schedule_date', now()->toDateString())
            ->first();

        if ($schedule) {
            $activeCheckin = Checkin::where('schedule_id', $schedule->schedule_id)
                ->whereNull('checkout_at')
                ->first();

            // Auto-Kick บุคลากรเดิมที่ค้างอยู่
            if ($activeCheckin) {
                $activeCheckin->checkout_at = now();
                $activeCheckin->duration_min = now()->diffInMinutes(Carbon::parse($activeCheckin->checkin_at));
                $activeCheckin->is_kicked = 1;
                $activeCheckin->save();
            }
        }

        $newCheckin = new Checkin();
        $newCheckin->schedule_id = $schedule ? $schedule->schedule_id : null;
        $newCheckin->staff_id = $request->staff_id;
        $newCheckin->checkin_at = now();
        $newCheckin->is_substitute = 0;
        $newCheckin->is_kicked = 0;
        $newCheckin->save();

        $staff = Staff::where('staff_id', $request->staff_id)->first();
        
        // ดึง Location (ตำแหน่งชั้น) จากตาราง counter หลัก
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

        // ดึงข้อมูลการประเมิน
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
            'negative'=> $evaluations->where('rating', '<=', 3)->count(),
            'current_counter_total' => $evaluations->count(),
            'current_counter_average' => $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '0.0',
        ];

        $reviewsList = $evaluations->map(function($eval) {
            // ป้องกัน Error กรณี Model Evaluation ไม่มี Timestamp
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