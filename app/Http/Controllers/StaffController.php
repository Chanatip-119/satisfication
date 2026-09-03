<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Checkin;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StaffController extends Controller
{
    public function index()
    {
        $staffs = Staff::all();
        return view('staff.index', compact('staffs'));
    }

    public function create()
    {
        return view('staff.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|integer',
            'staff_name' => 'required|string|max:255',
            'staff_email' => 'required|string|max:255',
            'staff_pincode' => 'required|string|max:255',
            'role_id' => 'required|integer'
        ]);

        Staff::create($request->all());

        return redirect()->route('staff.index')->with('success', 'เพิ่มข้อมูลบุคลากรสำเร็จ');
    }

    public function show($id)
    {
        $staff = Staff::with('role')->findOrFail($id);
        
        $checkins = Checkin::with(['schedule'])->where('staff_id', $id)->orderBy('checkin_at', 'desc')->get();
        $checkinIds = $checkins->pluck('checkin_id');
        
        $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->orderBy('created_at', 'desc')->get();

        $activeCheckin = $checkins->whereNull('checkout_at')->first();
        $status = $activeCheckin ? 'Active' : 'Offline';
        $currentCounter = $activeCheckin && $activeCheckin->schedule ? '1.' . $activeCheckin->schedule->counter_sub_id : '-';
        $timeRange = $activeCheckin && $activeCheckin->schedule ? substr($activeCheckin->schedule->start_time, 0, 5) . '–' . substr($activeCheckin->schedule->end_time, 0, 5) : '-';

        $avgRating = $evaluations->avg('rating') ? number_format($evaluations->avg('rating'), 1) : '-';
        $totalEvals = $evaluations->count();
        $totalComments = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->count();
        
        $ratingCounts = [
            5 => $evaluations->where('rating', 5)->count(),
            4 => $evaluations->where('rating', 4)->count(),
            3 => $evaluations->where('rating', 3)->count(),
            2 => $evaluations->where('rating', 2)->count(),
            1 => $evaluations->where('rating', 1)->count(),
        ];

        $history = $checkins->take(7)->map(function($c) use ($evaluations) {
            $evs = $evaluations->where('checkin_id', $c->checkin_id);
            return [
                'date' => Carbon::parse($c->checkin_at)->translatedFormat('j M.'),
                'counter' => $c->schedule ? 'เคาน์เตอร์ 1.' . $c->schedule->counter_sub_id : 'ไม่ระบุ',
                'time' => $c->schedule ? substr($c->schedule->start_time, 0, 5) . '-' . substr($c->schedule->end_time, 0, 5) . ' น.' : '-',
                'avg' => $evs->avg('rating') ? number_format($evs->avg('rating'), 1) : '-',
                'count' => $evs->count()
            ];
        });

        $comments = $evaluations->whereNotNull('comment')->where('comment', '!=', '')->take(10)->map(function($e) use ($checkins) {
            $c = $checkins->where('checkin_id', $e->checkin_id)->first();
            return [
                'counter' => $c && $c->schedule ? 'เคาน์เตอร์ 1.' . $c->schedule->counter_sub_id : 'ไม่ระบุ',
                'time' => Carbon::parse($e->created_at)->format('H:i น.'),
                'text' => $e->comment,
                'rating' => $e->rating
            ];
        });

        return response()->json([
            'name' => $staff->staff_name,
            'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) . ' · ' . ($staff->role ? $staff->role->role_name : 'Staff'),
            'pin' => $staff->staff_pincode,
            'avg_rating' => $avgRating,
            'total_checkins' => $checkins->count(),
            'total_comments' => $totalComments,
            'status' => $status,
            'current_counter' => $currentCounter,
            'time_range' => $timeRange,
            'rating_counts' => $ratingCounts,
            'total_evals' => $totalEvals > 0 ? $totalEvals : 1, 
            'history' => $history,
            'comments' => $comments
        ]);
    }
}