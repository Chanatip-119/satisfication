<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Counter;
use App\Models\Evaluation;
use App\Models\Schedule;
use App\Models\Staff;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function counterReport()
    {
        $evaluations = Evaluation::all();
        $totalAvg = $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '0.0';
        $totalEvals = $evaluations->count();
        $totalComments = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->count();
        $totalStaff = Checkin::distinct('staff_id')->count('staff_id');

        $countersList = [];
        $counters = Counter::with('countersubs')->get();

        foreach ($counters as $counter) {
            foreach ($counter->countersubs as $sub) {
                $checkinIds = Checkin::whereHas('schedule', function($q) use ($sub) {
                    $q->where('counter_sub_id', $sub->counter_sub_id);
                })->pluck('checkin_id');

                $subEvaluations = Evaluation::whereIn('checkin_id', $checkinIds);
                
                $avg = $subEvaluations->count() > 0 ? number_format($subEvaluations->avg('rating'), 1) : '0.0';
                $count = $subEvaluations->count();

                $activeCheckin = Checkin::whereHas('schedule', function($q) use ($sub) {
                    $q->where('counter_sub_id', $sub->counter_sub_id);
                })->whereNull('checkout_at')->first();

                $currentStaffName = '— ไม่มี Staff';
                $status = 'Close';
                
                if ($activeCheckin && $activeCheckin->staff) {
                    $currentStaffName = $activeCheckin->staff->staff_name;
                    $status = 'Active';
                }

                $schedule = Schedule::where('counter_sub_id', $sub->counter_sub_id)->first();
                $timeRange = $schedule ? substr($schedule->start_time, 0, 5) . '–' . substr($schedule->end_time, 0, 5) : '08:00–16:00';

                $countersList[] = (object)[
                    'counter_name' => $counter->counter_location ?? ('เคาน์เตอร์ ' . $counter->counter_id),
                    'name' => $counter->counter_id . '.' . $sub->counter_sub_id,
                    'time' => $timeRange,
                    'avg_rating' => $avg,
                    'eval_count' => $count,
                    'current_staff' => $currentStaffName,
                    'status' => $status
                ];
            }
        }

        return view('report.counter', compact('totalAvg', 'totalEvals', 'totalComments', 'totalStaff', 'countersList', 'counters'));
    }

    public function staffReport()
    {
        $staffs = Staff::all();
        $staffReportList = [];

        foreach ($staffs as $staff) {
            $checkins = Checkin::where('staff_id', $staff->staff_id)->get();
            $checkinIds = $checkins->pluck('checkin_id');
            
            $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->get();
            
            $avgRating = $evaluations->count() > 0 ? number_format($evaluations->avg('rating'), 1) : '-';
            $commentCount = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->count();
            $checkinCount = $checkins->count();
            
            // ตรวจสอบว่าพนักงานมีรายการ Check-in ที่ยังไม่ Checkout (checkout_at เป็น null) หรือไม่
            $activeCheckin = $checkins->whereNull('checkout_at')->first();
            $status = $activeCheckin ? 'Active' : 'Offline';

            $staffReportList[] = (object)[
                'staff_id' => $staff->staff_id,
                'name' => $staff->staff_name,
                'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT),
                'avg_rating' => $avgRating,
                'checkin_count' => $checkinCount,
                'comment_count' => $commentCount,
                'status' => $status,
                'raw_rating' => $evaluations->avg('rating')
            ];
        }

        return view('report.staff', compact('staffReportList'));
    }

    /**
     * ดึงข้อมูลรายละเอียด Staff สำหรับแสดงใน Side Panel
     */
    public function show($id)
    {
        $authUser = auth()->user();

        // ถ้าผู้ใช้เป็น Employee ให้บังคับใช้ ID ของตัวเองทันที เพื่อความปลอดภัย
        if ($authUser && method_exists($authUser, 'isEmployee') && $authUser->isEmployee()) {
            $id = $authUser->staff_id ?? $authUser->id;
        }

        $staff = Staff::where('staff_id', $id)->orWhere('id', $id)->first();

        if (!$staff) {
            return response()->json(['message' => 'ไม่พบข้อมูลบุคลากร'], 404);
        }

        $checkins = Checkin::where('staff_id', $staff->staff_id ?? $id)->get();
        $checkinIds = $checkins->pluck('checkin_id');
        $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->get();

        $totalEvals = $evaluations->count();
        $avgRating = $totalEvals > 0 ? number_format($evaluations->avg('rating'), 1) : '-';

        $activeCheckin = $checkins->whereNull('checkout_at')->first();
        $status = $activeCheckin ? 'Active' : 'Offline';
        $currentCounter = '-';
        $timeRange = '-';

        if ($activeCheckin) {
            $schedule = Schedule::where('schedule_id', $activeCheckin->schedule_id)->first() 
                        ?? Schedule::where('counter_sub_id', $activeCheckin->counter_sub_id ?? 1)->first();
            
            if ($schedule) {
                $currentCounter = 'เคาน์เตอร์ ' . ($schedule->counter_sub_id ?? $schedule->counter_id);
                $timeRange = substr($schedule->start_time ?? '08:00', 0, 5) . ' - ' . substr($schedule->end_time ?? '16:00', 0, 5);
            } else {
                $currentCounter = 'กำลังปฏิบัติงาน';
            }
        }

        $ratingCounts = [
            5 => $evaluations->where('rating', 5)->count(),
            4 => $evaluations->where('rating', 4)->count(),
            3 => $evaluations->where('rating', 3)->count(),
            2 => $evaluations->where('rating', 2)->count(),
            1 => $evaluations->where('rating', 1)->count(),
        ];

        $comments = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->sortByDesc('created_at')->map(function($e) {
            return [
                'counter' => 'เคาน์เตอร์บริการ',
                'time' => $e->created_at ? $e->created_at->format('d M Y H:i') : '',
                'text' => $e->comment,
                'rating' => (int)($e->rating ?? 5)
            ];
        })->values();

        $history = $checkins->sortByDesc('created_at')->take(7)->map(function($h) {
            $subEvals = Evaluation::where('checkin_id', $h->checkin_id);
            return [
                'date' => $h->created_at ? $h->created_at->format('d M Y') : '-',
                'counter' => 'เคาน์เตอร์ ' . ($h->counter_sub_id ?? '1'),
                'avg' => $subEvals->count() > 0 ? number_format($subEvals->avg('rating'), 1) : '-',
                'count' => $subEvals->count(),
                'time' => ($h->start_time && $h->end_time) ? ($h->start_time . ' - ' . $h->end_time) : '08:00 - 16:00'
            ];
        })->values();

        return response()->json([
            'name' => $staff->staff_name,
            'code' => 'BUU-' . str_pad($staff->staff_id ?? $staff->id, 3, '0', STR_PAD_LEFT),
            'department' => $staff->department ?? '-',  
            'position' => $staff->position ?? '-',      
            'pin' => ($authUser && method_exists($authUser, 'isEmployee') && $authUser->isEmployee()) ? null : ($staff->staff_pincode ?? '-'),
            'avg_rating' => $avgRating,
            'total_checkins' => $checkins->count(),
            'total_comments' => $comments->count(),
            'current_counter' => $currentCounter,
            'time_range' => $timeRange,
            'status' => $status,
            'total_evals' => $totalEvals,
            'rating_counts' => $ratingCounts,
            'comments' => $comments,
            'history' => $history
        ]);
    }
}