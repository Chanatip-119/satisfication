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
            
            $activeCheckin = $checkins->whereNull('checkout_at')->first();
            $status = $activeCheckin ? 'Check-in' : 'Check-out';

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
}