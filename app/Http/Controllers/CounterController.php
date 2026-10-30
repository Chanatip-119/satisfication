<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterSub;
use App\Models\Schedule;
use App\Models\Checkin;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CounterController extends Controller
{
    public function index()
    {
        // [แก้ไข ระดับ 2 ข้อ 8] โหลดเฉพาะจุดย่อย ไม่ต้องโหลดกะทั้งหมดแล้ว
        // เดิม: Counter::with(['countersubs.schedules' => fn ($q) => $q->orderBy('schedule_date', 'desc')])->get();
        // เหตุผล: หน้าเคาน์เตอร์ไม่ได้ใช้เวลาจากกะอีกต่อไป (ดู store/update) และการโหลดกะทุกแถวตั้งแต่เริ่มใช้ระบบ
        //        ทำให้หน้านี้ช้าลงเรื่อย ๆ และฝังข้อมูลกะทั้งหมดลงในปุ่มแก้ไขของทุกแถว
        $counters = Counter::with('countersubs')->get();
        
        return view('counter.index', compact('counters'));
    }

    public function create()
    {
        return view('counter.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'counter_id' => 'required|string|unique:counter,counter_id',
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
            // [รายงาน+Online ข้อ 4] ประเภทเคาน์เตอร์: 0 = ปกติ (มีคน Check-in), 1 = Online (รับประเมินตลอด)
            'is_online' => 'nullable|boolean',
        ]);
        $validated['is_online'] = (bool) ($validated['is_online'] ?? false);

        DB::transaction(function () use ($validated, $request) {
            $counter = Counter::create($validated);

            // ===== [แก้ไข ระดับ 2 ข้อ 8] สร้างจุดย่อยโดยไม่สร้างแถวในตาราง schedule =====
            // เดิม: วนตาม new_start_time[] แล้วเรียก Schedule::updateOrCreate(['counter_sub_id', 'schedule_date' => วันนี้], [เวลา])
            // ปัญหา: 1) สร้างแถว "กะที่ไม่มีคน" ลงในตาราง schedule ปนกับกะของบุคลากร
            //        2) ถ้าวันนี้จุดนั้นมีกะของบุคลากรอยู่แล้ว จะไปแก้เวลากะของคนนั้นแทน
            // ใหม่: วนตาม new_is_active[] และสร้างแค่ counter_sub
            if ($request->has('new_is_active')) {
                foreach ($request->new_is_active as $isActive) {
                    $counter->countersubs()->create([
                        'is_active' => $isActive ?? 1
                    ]);
                }
            }
            // ===== [จบ ข้อ 8: store] =====
        });

        return redirect()->route('counter.index')->with('success', 'เพิ่มข้อมูลเคาน์เตอร์สำเร็จ');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'counter_location' => 'required|string|max:255',
            'is_active' => 'required|boolean',
            // [รายงาน+Online ข้อ 4] ประเภทเคาน์เตอร์
            'is_online' => 'nullable|boolean',
        ]);
        $validated['is_online'] = (bool) ($validated['is_online'] ?? false);

        $counter = Counter::findOrFail($id);

        // ===== [รายงาน+Online ข้อ 4] เปลี่ยนเคาน์เตอร์ปกติเป็น Online ได้เมื่อไม่มีคนนั่งและไม่มีกะค้างอยู่ =====
        // ไม่งั้นกะของบุคลากรที่จัดไว้จะหายจากหน้าตาราง และคนที่กำลัง Check-in อยู่จะค้าง
        if ($validated['is_online'] && !$counter->is_online) {
            $subIds = $counter->countersubs()->pluck('counter_sub_id');
            $hasActiveCheckin = Checkin::whereNull('checkout_at')
                ->whereHas('schedule', fn ($q) => $q->whereIn('counter_sub_id', $subIds))
                ->exists();
            $upcomingCount = Schedule::whereIn('counter_sub_id', $subIds)
                ->where('schedule_date', '>=', \App\Services\CheckinService::now()->toDateString())
                ->count();

            if ($hasActiveCheckin || $upcomingCount > 0) {
                $reason = $hasActiveCheckin ? 'มีบุคลากรกำลัง Check-in อยู่' : "มีกะของบุคลากรตั้งแต่วันนี้เป็นต้นไป {$upcomingCount} กะ";
                return redirect()->route('counter.index')->withErrors([
                    "เปลี่ยนเป็นเคาน์เตอร์ Online ไม่ได้ เพราะ{$reason} — กรุณาย้ายหรือลบกะเหล่านั้นก่อน",
                ]);
            }
        }
        // ===== [จบ ข้อ 4: ตรวจก่อนเปลี่ยนเป็น Online] =====

        DB::transaction(function () use ($counter, $validated, $request) {
            $counter->update($validated);

            // ===== [แก้ไข ระดับ 2 ข้อ 8] เพิ่มจุดย่อยใหม่โดยไม่แตะตาราง schedule =====
            // เดิม: วนตาม new_start_time[] แล้วเรียก Schedule::updateOrCreate(...) เหมือนใน store()
            if ($request->has('new_is_active')) {
                foreach ($request->new_is_active as $isActive) {
                    $counter->countersubs()->create([
                        'is_active' => $isActive ?? 1
                    ]);
                }
            }
            // ===== [จบ ข้อ 8: เพิ่มจุดย่อย] =====

            // ===== [แก้ไข ระดับ 2 ข้อ 8] อัปเดตเฉพาะสถานะของจุดย่อยเดิม =====
            // เดิม: นอกจากอัปเดต is_active แล้ว ยังเรียก Schedule::updateOrCreate(['counter_sub_id', 'schedule_date' => วันนี้], [เวลาจากช่องที่ซ่อนไว้])
            // ปัญหา: แค่กดบันทึกหน้าเคาน์เตอร์ (แม้แก้แค่ชื่อตำแหน่ง) ก็ไปเปลี่ยนเวลากะของบุคลากรที่ทำงานวันนี้ได้
            //        โดยผู้ใช้มองไม่เห็นช่องเวลาเลย
            if ($request->has('existing_sub_id')) {
                foreach ($request->existing_sub_id as $index => $subId) {
                    if (isset($request->existing_is_active[$index])) {
                        $counter->countersubs()->where('counter_sub_id', $subId)->update([
                            'is_active' => $request->existing_is_active[$index]
                        ]);
                    }
                }
            }
            // ===== [จบ ข้อ 8: อัปเดตจุดย่อยเดิม] =====
        });

        return redirect()->route('counter.index')->with('success', 'อัปเดตข้อมูลเคาน์เตอร์สำเร็จ');
    }

    public function destroy($id)
    {
        $counter = Counter::findOrFail($id);

        DB::transaction(function () use ($counter) {
            $subIds = $counter->countersubs()->pluck('counter_sub_id');

            if ($subIds->isNotEmpty()) {
                $scheduleIds = Schedule::whereIn('counter_sub_id', $subIds)->pluck('schedule_id');

                if ($scheduleIds->isNotEmpty()) {
                    $checkinIds = Checkin::whereIn('schedule_id', $scheduleIds)->pluck('checkin_id');

                    if ($checkinIds->isNotEmpty()) {
                        Evaluation::whereIn('checkin_id', $checkinIds)->delete();
                        Checkin::whereIn('schedule_id', $scheduleIds)->delete();
                    }

                    Schedule::whereIn('counter_sub_id', $subIds)->delete();
                }

                $counter->countersubs()->delete();
            }

            $counter->delete();
        });

        return redirect()->route('counter.index')->with('success', 'ลบเคาน์เตอร์และข้อมูลที่เกี่ยวข้องทั้งหมดสำเร็จ');
    }

    public function storeSub(Request $request, $counterId)
    {
        // ย้ายการทำงานไปรวมไว้ใน store และ update แล้ว (เว้นไว้เป็น Fallback ได้)
        return redirect()->route('counter.index');
    }

    public function destroySub($counterId, $subId)
    {
        $counterSub = CounterSub::where('counter_id', $counterId)
                                ->where('counter_sub_id', $subId)
                                ->first();

        if ($counterSub) {
            DB::transaction(function () use ($counterSub, $subId) {
                $scheduleIds = Schedule::where('counter_sub_id', $subId)->pluck('schedule_id');
                
                if ($scheduleIds->isNotEmpty()) {
                    $checkinIds = Checkin::whereIn('schedule_id', $scheduleIds)->pluck('checkin_id');
                    if ($checkinIds->isNotEmpty()) {
                        Evaluation::whereIn('checkin_id', $checkinIds)->delete();
                        Checkin::whereIn('schedule_id', $scheduleIds)->delete();
                    }
                    Schedule::where('counter_sub_id', $subId)->delete();
                }

                $counterSub->delete();
            });
        }

        return redirect()->route('counter.index')->with('success', 'ลบเคาน์เตอร์ย่อยสำเร็จ');
    }
}