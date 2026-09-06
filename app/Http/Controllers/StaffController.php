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
    public function index(Request $request)
    {
        $query = Staff::with('role');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('staff_name', 'LIKE', "%{$search}%")
                  ->orWhere('staff_id', 'LIKE', "%{$search}%")
                  ->orWhereHas('role', function($roleQuery) use ($search) {
                      $roleQuery->where('role_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $staffs = $query->get();

        foreach ($staffs as $staff) {
            $checkinIds = \App\Models\Checkin::where('staff_id', $staff->staff_id)->pluck('checkin_id');
            $evaluations = \App\Models\Evaluation::whereIn('checkin_id', $checkinIds)->get();
            $staff->avg_rating = $evaluations->count() > 0
                ? number_format($evaluations->avg('rating'), 1)
                : '-';
        }

        return view('staff.index', compact('staffs'));
    }

    public function create()
    {
        return view('staff.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|string|max:255',
            'staff_name' => 'required|string|max:255',
            'staff_email' => 'required|string|max:255',
            'staff_pincode' => 'required|string|max:255',
            'role_id' => 'required|integer'
        ]);

        
        $staffIdNum = (int) preg_replace('/[^0-9]/', '', $request->staff_id);

        if ($staffIdNum <= 0) {
            return redirect()->back()->withErrors(['staff_id' => 'รหัสพนักงานต้องมีตัวเลข']);
        }

        if (Staff::where('staff_id', $staffIdNum)->exists()) {
            return redirect()->back()->withErrors(['staff_id' => 'รหัสพนักงาน ' . $staffIdNum . ' ถูกใช้แล้ว']);
        }

        $staff = new Staff();
        $staff->staff_id = $staffIdNum;
        $staff->staff_name = $request->staff_name;
        $staff->staff_email = $request->staff_email;
        $staff->staff_pincode = $request->staff_pincode;
        $staff->role_id = $request->role_id;
        $staff->save();

        return redirect()->route('staff.index')->with('success', 'เพิ่มข้อมูลบุคลากรสำเร็จ');
    }

    public function show($id)
    {
        $staff = Staff::with('role')->findOrFail($id);
        
        $checkins = Checkin::with(['schedule'])->where('staff_id', $id)->orderBy('checkin_at', 'desc')->get();
        $checkinIds = $checkins->pluck('checkin_id');
        
        $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->orderBy('evaluation_at', 'desc')->get();

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
                'time' => Carbon::parse($e->evaluation_at)->format('H:i น.'),
                'text' => $e->comment,
                'rating' => $e->rating
            ];
        });

        return response()->json([
            'name' => $staff->staff_name,
            'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT),
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

    public function update(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|integer|in:1,2,3,4'
        ]);

        $staff = Staff::findOrFail($id);
        $staff->role_id = $request->role_id;
        $staff->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'อัปเดตสิทธิ์สำเร็จ']);
        }

        return redirect()->route('staff.index')->with('success', 'อัปเดตสิทธิ์สำเร็จ');
    }

    public function destroy($id)
    {
        $staff = Staff::findOrFail($id);
        $staff->delete();

        return redirect()->route('staff.index')->with('success', 'ลบบุคลากรสำเร็จ');
    }

    public function resetAllPins(Request $request)
    {
        $request->validate([
            'reset_type' => 'required|in:now,schedule'
        ]);

        if ($request->reset_type === 'now') {
            $staffs = Staff::all();
            
            $usedPins = []; 

            foreach ($staffs as $staff) {
                do {
                    $newPin = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                } while (in_array($newPin, $usedPins));

                $usedPins[] = $newPin; 

                $staff->staff_pincode = $newPin;
                $staff->save();
            }
            
            return redirect()->route('staff.index')->with('success', 'รีเซ็ต PIN โค้ดทั้งหมดและส่งอีเมลเรียบร้อยแล้ว');
        } else {
            return redirect()->route('staff.index')->with('success', 'บันทึกการตั้งค่าการรีเซ็ตล่วงหน้าเรียบร้อยแล้ว');
        }
    }
}