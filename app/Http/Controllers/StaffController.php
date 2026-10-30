<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Role;
use App\Models\Checkin;
use App\Models\Evaluation;
use App\Models\CounterSub; // [แก้ไข ระดับ 3 ข้อ 16] ใช้สร้างชื่อเคาน์เตอร์ (เช่น 8.1)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Staff::with('role');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('staff_name', 'like', '%' . $search . '%')
                    ->orWhere('staff_email', 'like', '%' . $search . '%')
                    ->orWhere('department', 'like', '%' . $search . '%')
                    ->orWhere('position', 'like', '%' . $search . '%')
                    ->orWhereHas('role', function ($r) use ($search) {
                        $r->where('role_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $staffs = $query->get();

        $loggedInStaffIds = $this->loggedInStaffIdSet();

        foreach ($staffs as $staff) {
            $checkinIds = Checkin::where('staff_id', $staff->staff_id)->pluck('checkin_id');
            $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->get();
            $staff->avg_rating = $evaluations->count() > 0
                ? number_format($evaluations->avg('rating'), 1)
                : '-';
            $staff->status = isset($loggedInStaffIds[$staff->staff_id]) ? 'Active' : 'Inactive';
        }

        if ($request->filled('status')) {
            $staffs = $staffs->where('status', $request->status)->values();
        }

        $roles = Role::orderBy('role_id')->get();

        return view('staff.index', compact('staffs', 'roles'));
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
            // [แก้ไข ระดับ 1 ข้อ 3] PIN ต้องเป็นตัวเลข 4 หลัก และห้ามซ้ำกับบุคลากรคนอื่น
            // เดิม: 'staff_pincode' => 'required|string|max:255',
            'staff_pincode' => ['required', 'digits:4', Rule::unique('staff', 'staff_pincode')],
            'password' => 'nullable|string|min:6',
            'role_id' => ['required', 'integer', Rule::exists('role', 'role_id')],
        ], [
            'password.min' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
            // [แก้ไข ระดับ 1 ข้อ 3] ข้อความแจ้งเตือนของ PIN
            'staff_pincode.digits' => 'PIN Code ต้องเป็นตัวเลข 4 หลัก',
            'staff_pincode.unique' => 'PIN Code นี้ถูกใช้โดยบุคลากรคนอื่นแล้ว กรุณาเลือก PIN ใหม่',
            'role_id.required' => 'กรุณาเลือกบทบาทของบุคลากร',
            'role_id.exists' => 'ไม่พบบทบาทนี้ในระบบ กรุณารีเฟรชหน้าแล้วเลือกใหม่',
        ]);

        // [สิทธิ์ตามบทบาท] เฉพาะ Executive เท่านั้นที่สร้างบุคลากรระดับ Executive ได้
        // (ไม่งั้น Admin สร้างบัญชี Executive ให้ตัวเองแล้วล็อกอินไปดู PIN ได้)
        $executiveRoleId = Staff::executiveRoleId();
        if (!auth()->user()->isExecutive() && $executiveRoleId !== null && (int) $request->role_id === $executiveRoleId) {
            return redirect()->back()->withErrors(['role_id' => 'เฉพาะ Executive เท่านั้นที่กำหนดสิทธิ์ Executive ได้'])->withInput();
        }

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
        $staff->password = !empty($request->password)
            ? Hash::make($request->password)
            : Hash::make('123456');
        $staff->role_id = $request->role_id;
        $staff->department = $request->department;
        $staff->position = $request->position;
        $staff->save();

        return redirect()->route('staff.index')->with('success', 'เพิ่มข้อมูลบุคลากรสำเร็จ');
    }

    public function show($id)
    {
        // ===== [แก้ไข ระดับ 1 ข้อ 6] พนักงาน (Employee) ดูได้เฉพาะข้อมูลของตัวเองเท่านั้น =====
        // เดิม: ไม่มีการตรวจสิทธิ์ พนักงานเปิด /staff/{id ของคนอื่น} ก็เห็นคะแนน ความคิดเห็น และประวัติของคนนั้นได้
        // เมื่อเรียกผ่าน fetch ที่ส่ง Accept: application/json จะได้ JSON { "message": "..." } กลับไป
        $authUser = auth()->user();
        if ($authUser->isEmployee() && (int) $authUser->staff_id !== (int) $id) {
            abort(403, 'คุณไม่มีสิทธิ์ดูข้อมูลของบุคลากรคนอื่น');
        }
        // ===== [จบ ข้อ 6] =====

        $staff = Staff::with('role')->findOrFail($id);

        // [แก้ไข ระดับ 4] ใช้ตัวช่วยกลาง CounterSub::labelFor() แทนการสร้างตารางเลขเองในเมธอดนี้
        // (เดิม [ระดับ 3 ข้อ 16] สร้าง $subCodeMap เองที่นี่ — ผลลัพธ์เหมือนกัน แต่ตอนนี้ทุกหน้าใช้โค้ดชุดเดียว)
        $counterLabel = function ($schedule) {
            return $schedule ? CounterSub::labelFor($schedule->counter_sub_id) : 'ไม่ระบุ';
        };
        
        $checkins = Checkin::with(['schedule'])->where('staff_id', $id)->orderBy('checkin_at', 'desc')->get();
        $checkinIds = $checkins->pluck('checkin_id');
        
        $evaluations = Evaluation::whereIn('checkin_id', $checkinIds)->orderBy('evaluation_at', 'desc')->get();

        $activeCheckin = $checkins->whereNull('checkout_at')->first();
        // [แก้ไข ระดับ 3 ข้อ 16] ใช้ค่าที่หน้าเว็บตรวจได้ตรงกัน ('Active' = กำลัง Check-in อยู่)
        // เดิม: $status = $activeCheckin ? 'Check-ins' : 'Offline';
        $status = $activeCheckin ? 'Active' : 'Offline';
        $loggedInStaffIds = $this->loggedInStaffIdSet();
        $accountStatus = isset($loggedInStaffIds[$staff->staff_id]) ? 'Active' : 'Inactive';
        // [แก้ไข ระดับ 3 ข้อ 16] เดิม: '1.' . $activeCheckin->schedule->counter_sub_id
        $currentCounter = $activeCheckin && $activeCheckin->schedule ? $counterLabel($activeCheckin->schedule) : '-';
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

        $history = $checkins->take(7)->map(function($c) use ($evaluations, $counterLabel) {
            $evs = $evaluations->where('checkin_id', $c->checkin_id);
            return [
                'date' => Carbon::parse($c->checkin_at)->translatedFormat('j M.'),
                // [แก้ไข ระดับ 3 ข้อ 16] เดิม: 'เคาน์เตอร์ 1.' . $c->schedule->counter_sub_id
                'counter' => $counterLabel($c->schedule),
                'time' => $c->schedule ? substr($c->schedule->start_time, 0, 5) . '-' . substr($c->schedule->end_time, 0, 5) . ' น.' : '-',
                'avg' => $evs->avg('rating') ? number_format($evs->avg('rating'), 1) : '-',
                'count' => $evs->count()
            ];
        });

        // [รายงาน+Online ข้อ 1] รวมการประเมินที่มีคำชี้แจงด้วย แม้ผู้ประเมินไม่ได้เขียนความคิดเห็น (ให้คะแนนอย่างเดียว)
        // เดิม: เฉพาะรายการที่มี comment
        $comments = $evaluations->filter(fn ($e) => trim((string) $e->comment) !== '' || trim((string) $e->staff_note) !== '')
            ->take(10)->map(function($e) use ($checkins, $counterLabel) {
            $c = $checkins->where('checkin_id', $e->checkin_id)->first();
            return [
                // [แก้ไข ระดับ 3 ข้อ 16] เดิม: 'เคาน์เตอร์ 1.' . $c->schedule->counter_sub_id
                'counter' => $c ? $counterLabel($c->schedule) : 'ไม่ระบุ',
                'time' => Carbon::parse($e->evaluation_at)->format('H:i น.'),
                'text' => $e->comment,
                'rating' => $e->rating,
                // [รายงาน+Online ข้อ 1] คำชี้แจงจากผู้ให้บริการ
                'note' => $e->staff_note,
                'note_time' => $e->staff_note_at ? Carbon::parse($e->staff_note_at)->translatedFormat('j M. H:i') : null,
            ];
        });

        return response()->json([
            'name' => $staff->staff_name,
            'code' => 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT),
            // [สิทธิ์ตามบทบาท] ส่ง PIN จริงเฉพาะ Executive คนอื่นได้ null (หน้าเว็บแสดง ●●●●)
            // เดิม: auth()->user()->isEmployee() ? null : $staff->staff_pincode (Admin ทุกคนเห็น PIN)
            'pin' => $authUser->isExecutive() ? $staff->staff_pincode : null,
            'department' => $staff->department,
            'position' => $staff->position,
            // [สิทธิ์ตามบทบาท] Employee ไม่เห็นคะแนนเฉลี่ย
            'avg_rating' => $authUser->isEmployee() ? null : $avgRating,
            'account_status' => $accountStatus,
            'total_checkins' => $checkins->count(),
            'total_comments' => $totalComments,
            'status' => $status,
            'current_counter' => $currentCounter,
            'time_range' => $timeRange,
            'rating_counts' => $ratingCounts,
            // [แก้ไข ระดับ 3 ข้อ 16] ส่งจำนวนจริง (หน้าเว็บกันหารศูนย์ไว้แล้ว)
            // เดิม: $totalEvals > 0 ? $totalEvals : 1 — คนที่ยังไม่มีการประเมินจะแสดงเป็น 1 รายการ
            'total_evals' => $totalEvals,
            'history' => $history,
            'comments' => $comments
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('role', 'role_id')],
        ], [
            'role_id.required' => 'กรุณาเลือกบทบาท',
            'role_id.exists' => 'ไม่พบบทบาทนี้ในระบบ กรุณารีเฟรชหน้าแล้วเลือกใหม่',
        ]);

        $staff = Staff::findOrFail($id);

        // ===== [สิทธิ์ตามบทบาท] เปลี่ยนสิทธิ์ที่เกี่ยวกับ Executive ได้เฉพาะ Executive =====
        // กัน 2 กรณี: Admin เลื่อนใคร (รวมถึงตัวเอง) เป็น Executive / Admin ลดสิทธิ์ Executive คนอื่น
        $executiveRoleId = Staff::executiveRoleId();
        if (!auth()->user()->isExecutive()
            && $executiveRoleId !== null
            && ((int) $staff->role_id === $executiveRoleId || (int) $validated['role_id'] === $executiveRoleId)) {
            $message = 'เฉพาะ Executive เท่านั้นที่เปลี่ยนสิทธิ์ที่เกี่ยวกับ Executive ได้';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }
            return redirect()->route('staff.index')->withErrors([$message]);
        }
        // ===== [จบ สิทธิ์ตามบทบาท: update] =====

        $staff->role_id = $validated['role_id'];
        $staff->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'อัปเดตสิทธิ์สำเร็จ']);
        }

        return redirect()->route('staff.index')->with('success', 'อัปเดตสิทธิ์สำเร็จ');
    }

    public function destroy($id)
    {
        $staff = Staff::findOrFail($id);

        // [สิทธิ์ตามบทบาท] ลบบัญชี Executive ได้เฉพาะ Executive
        if ($staff->isExecutive() && !auth()->user()->isExecutive()) {
            return redirect()->route('staff.index')->withErrors(['เฉพาะ Executive เท่านั้นที่ลบบัญชี Executive ได้']);
        }

        DB::transaction(function () use ($staff) {
            // ลบ Evaluation ที่เกี่ยวข้องกับ Checkin ของพนักงานคนนี้
            $checkinIds = Checkin::where('staff_id', $staff->staff_id)->pluck('checkin_id');
            if ($checkinIds->isNotEmpty()) {
                Evaluation::whereIn('checkin_id', $checkinIds)->delete();
            }

            // ลบ Checkin ของพนักงานคนนี้
            Checkin::where('staff_id', $staff->staff_id)->delete();

            // ลบ Staff
            $staff->delete();
        });

        return redirect()->route('staff.index')->with('success', 'ลบบุคลากรสำเร็จ');
    }

    /**
     * Export staff data as CSV
     */
    public function exportCsv()
    {
        $staffs = Staff::with('role')->get();

        // [สิทธิ์ตามบทบาท] PIN ในไฟล์ CSV เฉพาะ Executive คนอื่นได้ **** แทน
        // (ถ้านำไฟล์นี้กลับมา Import ช่อง **** จะถูกมองว่าไม่ได้กรอก PIN และระบบคง PIN เดิมไว้)
        $canSeePin = auth()->user()->isExecutive();

        $filename = 'staff_export_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($staffs, $canSeePin) {
            $file = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($file, [
                'staff_id',
                'staff_name',
                'staff_email',
                'staff_pincode',
                'role_id',
                'role_name',
                'department',
                'position',
            ]);

            foreach ($staffs as $staff) {
                fputcsv($file, [
                    $staff->staff_id,
                    $staff->staff_name,
                    $staff->staff_email,
                    $canSeePin ? $staff->staff_pincode : '****', // [สิทธิ์ตามบทบาท]
                    $staff->role_id,
                    $staff->role ? $staff->role->role_name : '',
                    $staff->department ?? '',
                    $staff->position ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download import template CSV
     */
    public function downloadTemplate()
    {
        $filename = 'staff_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            // BOM สำหรับเปิดใน Excel ภาษาไทยได้ถูกต้อง
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($file, [
                'staff_id',
                'staff_name',
                'staff_email',
                'staff_pincode',
                'password',
                'role_id',
                'department',
                'position',
            ]);

            // ตัวอย่างข้อมูล
            fputcsv($file, [
                '10',
                'นายสมชาย มีสุข',
                'somchai@go.buu.ac.th',
                '1234',
                'password123',
                '4',
                'ฝ่ายบริการ',
                'บรรณารักษ์',
            ]);
            fputcsv($file, [
                '11',
                'นางสาวสมใจ ดีงาม',
                'somjai@go.buu.ac.th',
                '5678',
                'mypassword456',
                '4',
                'ฝ่ายวิชาการ',
                'เจ้าหน้าที่ธุรการ',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import staff from uploaded CSV/Excel file
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|max:10240',
        ], [
            'excel_file.required' => 'กรุณาเลือกไฟล์ที่ต้องการนำเข้า',
            'excel_file.file' => 'ไฟล์ที่อัปโหลดไม่ถูกต้อง',
            'excel_file.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB',
        ]);

        $file = $request->file('excel_file');
        $extension = strtolower(trim($file->getClientOriginalExtension()));

        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'])) {
            return redirect()->route('staff.index')->with('error', 'รูปแบบไฟล์ไม่ถูกต้อง รองรับเฉพาะไฟล์นามสกุล .csv, .xlsx, .xls');
        }

        $rows = [];
        $errorDetail = '';

        if (in_array($extension, ['csv', 'txt'])) {
            $rows = $this->parseCsv($file->getRealPath());
        } elseif (in_array($extension, ['xlsx', 'xls', 'xlsm'])) {
            $rows = $this->parseXlsx($file, $errorDetail);
            if ($rows === false) {
                return redirect()->route('staff.index')->with('error', 'ไม่สามารถอ่านไฟล์ Excel ได้' . ($errorDetail ? ": {$errorDetail}" : ' โปรดลองบันทึกเป็น .csv แล้วลองใหม่อีกครั้ง'));
            }
        }

        if (empty($rows)) {
            return redirect()->route('staff.index')->with('error', 'ไม่พบข้อมูลในไฟล์');
        }

        // ค้นหาและเตรียมโครงสร้างบทบาท (Roles) ในระบบ
        $allRoles = Role::all();
        $roleMap = [];
        foreach ($allRoles as $r) {
            $roleMap[(string) $r->role_id] = (int) $r->role_id;
            $roleMap[mb_strtolower(trim($r->role_name), 'UTF-8')] = (int) $r->role_id;
        }

        // ตรวจสอบว่าแถวแรกเป็น Header หรือไม่ และแมปคอลัมน์อัตโนมัติ
        $colMap = [
            'staff_id' => null,
            'staff_name' => null,
            'staff_email' => null,
            'staff_pincode' => null,
            'password' => null,
            'role_id' => null,
            'department' => null,
            'position' => null,
        ];

        $hasHeader = false;
        if (count($rows) > 0) {
            $firstRow = $rows[0];
            $firstCellVal = trim((string)($firstRow[0] ?? ''));
            $firstCellIsNumber = is_numeric($firstCellVal) || preg_match('/^\d+$/', $firstCellVal);

            // ถ้าเซลล์แรกเป็นตัวเลข (เช่น 22, 10, 1) แถวแรกเป็นแถวข้อมูล ไม่ใช่หัวตาราง
            if (!$firstCellIsNumber) {
                $matchCount = 0;
                $tempColMap = $colMap;

                foreach ($firstRow as $cIdx => $cell) {
                    $rawVal = trim((string)$cell);
                    $lowerVal = mb_strtolower($rawVal, 'UTF-8');
                    $cleanVal = preg_replace('/[\s_\-]+/u', '', $lowerVal);

                    if (in_array($cleanVal, ['staffid', 'id', 'รหัสพนักงาน', 'รหัส', 'code', 'staffcode', 'รหัสบุคลากร']) || str_contains($lowerVal, 'staff_id') || str_contains($lowerVal, 'รหัสพนักงาน')) {
                        $tempColMap['staff_id'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['staffname', 'name', 'fullname', 'ชื่อ', 'ชื่อนามสกุล', 'ชื่อพนักงาน', 'ชื่อบุคลากร']) || str_contains($lowerVal, 'staff_name') || $cleanVal === 'ชื่อ' || $cleanVal === 'ชื่อนามสกุล') {
                        $tempColMap['staff_name'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['staffemail', 'email', 'mail', 'อีเมล', 'e-mail', 'เมล']) || str_contains($lowerVal, 'staff_email') || str_contains($lowerVal, 'อีเมล') || $cleanVal === 'email') {
                        $tempColMap['staff_email'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['staffpincode', 'pincode', 'pin', 'พิน', 'รหัสพิน', 'รหัสpin']) || str_contains($lowerVal, 'pincode') || str_contains($lowerVal, 'รหัสพิน')) {
                        $tempColMap['staff_pincode'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['password', 'pass', 'pwd', 'รหัสผ่าน']) || str_contains($lowerVal, 'password') || str_contains($lowerVal, 'รหัสผ่าน')) {
                        $tempColMap['password'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['roleid', 'role', 'บทบาท', 'สิทธิ์', 'ระดับสิทธิ์']) || str_contains($lowerVal, 'role') || str_contains($lowerVal, 'บทบาท') || str_contains($lowerVal, 'สิทธิ์')) {
                        $tempColMap['role_id'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['department', 'dept', 'ฝ่าย', 'แผนก', 'หน่วยงาน', 'สังกัด', 'คณะ', 'สาขา', 'กอง'])
                        || str_contains($lowerVal, 'department')
                        || str_contains($lowerVal, 'ฝ่าย') || str_contains($lowerVal, 'แผนก')
                        || str_contains($lowerVal, 'สังกัด') || str_contains($lowerVal, 'หน่วยงาน')) {
                        $tempColMap['department'] = $cIdx;
                        $matchCount++;
                    } elseif (in_array($cleanVal, ['position', 'pos', 'ตำแหน่ง', 'หน้าที่', 'สายงาน', 'job', 'jobposition'])
                        || str_contains($lowerVal, 'position')
                        || str_contains($lowerVal, 'ตำแหน่ง') || str_contains($lowerVal, 'หน้าที่')
                        || str_contains($lowerVal, 'สายงาน')) {
                        $tempColMap['position'] = $cIdx;
                        $matchCount++;
                    }
                }

                if ($matchCount >= 2 || ($matchCount >= 1 && ($tempColMap['staff_id'] !== null || $tempColMap['staff_name'] !== null))) {
                    $hasHeader = true;
                    $colMap = $tempColMap;
                    array_shift($rows);
                }
            }
        }

        // [สิทธิ์ตามบทบาท] ผู้ที่ Import เป็น Executive หรือไม่ (ใช้กับการกำหนดสิทธิ์และข้อความ PIN)
        $importerIsExecutive = auth()->user()->isExecutive();
        $executiveRoleId = Staff::executiveRoleId();

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $hasHeader ? ($index + 2) : ($index + 1);

            // ดึงค่าตาม colMap ถ้ามี Header หรือใช้ตำแหน่งมาตรฐาน
            if ($hasHeader && ($colMap['staff_id'] !== null || $colMap['staff_name'] !== null)) {
                $staffIdRaw   = ($colMap['staff_id'] !== null && isset($row[$colMap['staff_id']])) ? trim((string)$row[$colMap['staff_id']]) : '';
                $staffName    = ($colMap['staff_name'] !== null && isset($row[$colMap['staff_name']])) ? trim((string)$row[$colMap['staff_name']]) : '';
                $staffEmail   = ($colMap['staff_email'] !== null && isset($row[$colMap['staff_email']])) ? trim((string)$row[$colMap['staff_email']]) : '';
                $staffPincode = ($colMap['staff_pincode'] !== null && isset($row[$colMap['staff_pincode']])) ? trim((string)$row[$colMap['staff_pincode']]) : '';
                $passwordRaw  = ($colMap['password'] !== null && isset($row[$colMap['password']])) ? trim((string)$row[$colMap['password']]) : '';
                $roleRaw      = ($colMap['role_id'] !== null && isset($row[$colMap['role_id']])) ? trim((string)$row[$colMap['role_id']]) : '';
                $departmentRaw = ($colMap['department'] !== null && isset($row[$colMap['department']])) ? trim((string)$row[$colMap['department']]) : '';
                $positionRaw   = ($colMap['position'] !== null && isset($row[$colMap['position']])) ? trim((string)$row[$colMap['position']]) : '';

                // Fallback ถ้าระบุ Header คอลัมน์อื่น แต่คอลัมน์ G (index 6) หรือ H (index 7) มีข้อมูล
                if ($colMap['department'] === null && isset($row[6])) {
                    $departmentRaw = trim((string)$row[6]);
                }
                if ($colMap['position'] === null && isset($row[7])) {
                    $positionRaw = trim((string)$row[7]);
                }
            } else {
                // Positional mapping ตาม Template 8 คอลัมน์:
                // 0: staff_id, 1: staff_name, 2: staff_email, 3: staff_pincode, 4: password, 5: role_id, 6: department, 7: position
                $staffIdRaw   = isset($row[0]) ? trim((string)$row[0]) : '';
                $staffName    = isset($row[1]) ? trim((string)$row[1]) : '';
                $staffEmail   = isset($row[2]) ? trim((string)$row[2]) : '';
                $staffPincode = isset($row[3]) ? trim((string)$row[3]) : '';

                if (count($row) >= 8) {
                    $passwordRaw   = isset($row[4]) ? trim((string)$row[4]) : '';
                    $roleRaw       = isset($row[5]) ? trim((string)$row[5]) : '';
                    $departmentRaw = isset($row[6]) ? trim((string)$row[6]) : '';
                    $positionRaw   = isset($row[7]) ? trim((string)$row[7]) : '';
                } elseif (count($row) === 7) {
                    // กรณีมี 7 คอลัมน์ (เช่น ไม่มี password หรือไม่มี position)
                    $col4 = isset($row[4]) ? trim((string)$row[4]) : '';
                    $col5 = isset($row[5]) ? trim((string)$row[5]) : '';
                    $col4IsRole = in_array($col4, ['1', '2', '3', '4']) || in_array(mb_strtolower($col4, 'UTF-8'), ['admin', 'supervisor', 'staff', 'employee']);
                    $col5IsRole = in_array($col5, ['1', '2', '3', '4']) || in_array(mb_strtolower($col5, 'UTF-8'), ['admin', 'supervisor', 'staff', 'employee']);

                    if ($col4IsRole && !$col5IsRole) {
                        $passwordRaw   = '';
                        $roleRaw       = $col4;
                        $departmentRaw = isset($row[5]) ? trim((string)$row[5]) : '';
                        $positionRaw   = isset($row[6]) ? trim((string)$row[6]) : '';
                    } else {
                        $passwordRaw   = $col4;
                        $roleRaw       = $col5;
                        $departmentRaw = isset($row[6]) ? trim((string)$row[6]) : '';
                        $positionRaw   = '';
                    }
                } elseif (count($row) === 6) {
                    $passwordRaw   = isset($row[4]) ? trim((string)$row[4]) : '';
                    $roleRaw       = isset($row[5]) ? trim((string)$row[5]) : '';
                    $departmentRaw = '';
                    $positionRaw   = '';
                } elseif (count($row) === 5) {
                    $col4 = isset($row[4]) ? trim((string)$row[4]) : '';
                    if (in_array($col4, ['1', '2', '3', '4']) || in_array(mb_strtolower($col4, 'UTF-8'), ['admin', 'supervisor', 'staff', 'employee'])) {
                        $passwordRaw = '';
                        $roleRaw     = $col4;
                    } else {
                        $passwordRaw = $col4;
                        $roleRaw     = '';
                    }
                    $departmentRaw = '';
                    $positionRaw   = '';
                } else {
                    $passwordRaw   = '';
                    $roleRaw       = '';
                    $departmentRaw = '';
                    $positionRaw   = '';
                }
            }

            // ข้ามแถวว่าง
            if (empty($staffIdRaw) && empty($staffName) && empty($staffEmail)) {
                continue;
            }

            if (empty($staffName)) {
                $errors[] = "แถวที่ {$rowNum}: ไม่มีชื่อ-นามสกุล";
                $skipped++;
                continue;
            }

            // แปลงรหัสพนักงาน (ถ้ามีระบุ เช่น BUU-010 หรือ 10)
            $staffIdNum = 0;
            if (!empty($staffIdRaw)) {
                $staffIdNum = (int) preg_replace('/[^0-9]/', '', $staffIdRaw);
                if ($staffIdNum <= 0) {
                    $errors[] = "แถวที่ {$rowNum}: รหัสพนักงานไม่ถูกต้อง (\"{$staffIdRaw}\")";
                    $skipped++;
                    continue;
                }
            }

            // แปลงบทบาท (Role)
            $roleId = Staff::ROLE_EMPLOYEE;
            if (!empty($roleRaw)) {
                $roleKey = mb_strtolower($roleRaw, 'UTF-8');
                if (isset($roleMap[$roleKey])) {
                    $roleId = $roleMap[$roleKey];
                } elseif (is_numeric($roleRaw) && in_array((int)$roleRaw, [1, 2, 3, 4])) {
                    $roleId = (int)$roleRaw;
                }
            }

            // ตรวจสอบบุคลากรเดิมในระบบ (ค้นหาจาก staff_id หรือ staff_email)
            $staff = null;
            if ($staffIdNum > 0) {
                $staff = Staff::where('staff_id', $staffIdNum)->first();
            }
            if (!$staff && !empty($staffEmail)) {
                $staff = Staff::where('staff_email', $staffEmail)->first();
            }

            // ===== [สิทธิ์ตามบทบาท] ไฟล์ Import แก้หรือตั้งสิทธิ์ Executive ได้เฉพาะ Executive =====
            if (!$importerIsExecutive && $executiveRoleId !== null
                && ($roleId === $executiveRoleId || ($staff && (int) $staff->role_id === $executiveRoleId))) {
                $errors[] = "แถวที่ {$rowNum}: เกี่ยวข้องกับสิทธิ์ Executive ซึ่งแก้ได้เฉพาะผู้ใช้ระดับ Executive (ข้าม)";
                $skipped++;
                continue;
            }
            // ===== [จบ สิทธิ์ตามบทบาท: Import] =====

            // ตรวจสอบอีเมลซ้ำกับบุคลากรคนอื่น
            if (!empty($staffEmail)) {
                $duplicateEmail = Staff::where('staff_email', $staffEmail)
                    ->when($staff, function ($q) use ($staff) {
                        $q->where('staff_id', '!=', $staff->staff_id);
                    })
                    ->first();
                if ($duplicateEmail) {
                    $errors[] = "แถวที่ {$rowNum}: อีเมล \"{$staffEmail}\" ซ้ำกับรหัสพนักงาน {$duplicateEmail->staff_id} (ข้าม)";
                    $skipped++;
                    continue;
                }
            }

            // ===== [แก้ไข ระดับ 1 ข้อ 3] จัดการ PIN Code (ต้องเป็นตัวเลข 4 หลัก และห้ามซ้ำกับคนอื่น) =====
            // เดิม: ถ้า PIN ไม่ครบ 4 หลักจะใช้ PIN เดิมหรือสุ่มด้วย rand() โดยไม่ตรวจซ้ำ
            //       และถ้า PIN ในไฟล์ซ้ำกับคนอื่นก็บันทึกไปเลย ทำให้ Check-in ผิดคนได้
            $cleanPin = preg_replace('/[^0-9]/', '', $staffPincode);
            if (strlen($cleanPin) !== 4) {
                $cleanPin = ($staff && !empty($staff->staff_pincode))
                    ? (string) $staff->staff_pincode
                    : Staff::generateUniquePin();
            }
            if (Staff::pinTakenByOther($cleanPin, $staff?->staff_id)) {
                $newPin = Staff::generateUniquePin();
                // [สิทธิ์ตามบทบาท] บอกเลข PIN ในข้อความเฉพาะเมื่อผู้ Import เป็น Executive
                $errors[] = $importerIsExecutive
                    ? "แถวที่ {$rowNum}: PIN {$cleanPin} ซ้ำกับบุคลากรคนอื่น ระบบสุ่ม PIN ใหม่ให้เป็น {$newPin} (นำเข้าสำเร็จ)"
                    : "แถวที่ {$rowNum}: PIN ในไฟล์ซ้ำกับบุคลากรคนอื่น ระบบสุ่ม PIN ใหม่ให้แล้ว (ผู้ใช้ระดับ Executive ดู PIN ได้ในหน้าบุคลากร) (นำเข้าสำเร็จ)";
                $cleanPin = $newPin;
            }
            // ===== [จบ ข้อ 3: PIN ตอน Import] =====

            // ตรวจสอบความยาว Password (หากมีการระบุ และไม่ใช่ Bcrypt Hash)
            if (!empty($passwordRaw) && !str_starts_with($passwordRaw, '$2y$') && !str_starts_with($passwordRaw, '$2a$')) {
                if (mb_strlen($passwordRaw, 'UTF-8') < 6) {
                    $errors[] = "แถวที่ {$rowNum}: รหัสผ่าน \"{$passwordRaw}\" สั้นเกินไป (ต้องมีความยาวอย่างน้อย 6 ตัวอักษร)";
                    $skipped++;
                    continue;
                }
            }

            try {
                $isUpdate = false;
                if ($staff) {
                    $isUpdate = true;
                } else {
                    $staff = new Staff();
                    if ($staffIdNum > 0) {
                        $staff->staff_id = $staffIdNum;
                    }
                }

                $staff->staff_name = $staffName;
                if (!empty($staffEmail)) {
                    $staff->staff_email = $staffEmail;
                }
                $staff->staff_pincode = $cleanPin;
                $staff->role_id = $roleId;

                // บันทึก department (ฝ่าย) และ position (ตำแหน่ง) ถ้ามีระบุ
                if ($departmentRaw !== '') {
                    $staff->department = $departmentRaw;
                } elseif (!$isUpdate) {
                    $staff->department = null;
                }

                if ($positionRaw !== '') {
                    $staff->position = $positionRaw;
                } elseif (!$isUpdate) {
                    $staff->position = null;
                }

                // จัดการ Password
                if (!empty($passwordRaw)) {
                    // หากใส่ Hash มาอยู่แล้ว (เช่น ขึ้นต้นด้วย $2y$ หรือ $2a$) ให้ใช้ค่านั้นเลย ไม่เช่นนั้น Hash::make
                    $staff->password = (str_starts_with($passwordRaw, '$2y$') || str_starts_with($passwordRaw, '$2a$'))
                        ? $passwordRaw
                        : Hash::make($passwordRaw);
                } elseif (!$isUpdate || empty($staff->password)) {
                    // หากเป็นรายการใหม่หรือยังไม่มีรหัสผ่าน ให้ใช้ 123456 เป็นรหัสผ่านเริ่มต้น (ขั้นต่ำ 6 ตัวอักษร)
                    $staff->password = Hash::make('123456');
                }

                $staff->save();

                if ($isUpdate) {
                    $updated++;
                } else {
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "แถวที่ {$rowNum}: เกิดข้อผิดพลาด - " . $e->getMessage();
                $skipped++;
            }
        }

        $messageParts = [];
        if ($imported > 0) {
            $messageParts[] = "เพิ่มข้อมูลใหม่ {$imported} คน";
        }
        if ($updated > 0) {
            $messageParts[] = "อัปเดตข้อมูล {$updated} คน";
        }
        if ($skipped > 0) {
            $messageParts[] = "ข้าม {$skipped} รายการ";
        }

        $message = "Import สำเร็จ: " . (!empty($messageParts) ? implode(', ', $messageParts) : "ไม่พบข้อมูลที่เปลี่ยนแปลง");

        if (!empty($errors)) {
            return redirect()->route('staff.index')
                ->with('success', $message)
                ->with('import_errors', $errors);
        }

        return redirect()->route('staff.index')->with('success', $message);
    }

    /**
     * Parse CSV file into array of rows with automatic encoding conversion (CP874 / Windows-874 / TIS-620 / UTF-16 to UTF-8)
     */
    private function parseCsv($filepath)
    {
        $content = @file_get_contents($filepath);
        if ($content === false || $content === '') {
            return [];
        }

        // ตรวจสอบและตัด BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (str_starts_with($content, "\xFF\xFE")) {
            $content = @iconv('UTF-16LE', 'UTF-8//IGNORE', substr($content, 2));
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($content, 2));
        }

        // หากไฟล์ไม่ได้เข้ารหัส UTF-8 (เช่น มาจาก Excel ภาษาไทยที่เป็น Windows-874 / CP874 / TIS-620) ให้แปลงเป็น UTF-8
        if (!mb_check_encoding($content, 'UTF-8') && function_exists('iconv')) {
            $converted = @iconv('CP874', 'UTF-8//IGNORE', $content);
            if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                $content = $converted;
            } else {
                $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
                if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                    $content = $converted;
                }
            }
        }

        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        while (($data = fgetcsv($stream)) !== false) {
            $cleanRow = array_map(function ($val) {
                return is_string($val) ? trim($val) : $val;
            }, $data);
            $rows[] = $cleanRow;
        }

        fclose($stream);
        return $rows;
    }

    /**
     * Try to parse XLSX/XLS file using PhpSpreadsheet or built-in ZipArchive parser
     */
    private function parseXlsx($file, &$errorDetail = '')
    {
        // 1. ลองใช้ PhpSpreadsheet ก่อนหากมี
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = [];
                foreach ($worksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getValue();
                    }
                    $rows[] = $rowData;
                }
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                $errorDetail = 'PhpSpreadsheet error: ' . $e->getMessage();
            }
        }

        // 2. ถ้าเป็นไฟล์ .xlsx และ PhpSpreadsheet ล้มเหลวหรือไม่มี ให้ใช้ Built-in ZipArchive + SimpleXML
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'xlsx') {
            try {
                $basicRows = $this->parseXlsxBasic($file->getRealPath());
                if ($basicRows !== false && !empty($basicRows)) {
                    return $basicRows;
                }
            } catch (\Throwable $e) {
                $errorDetail .= ($errorDetail ? ' | ' : '') . 'Built-in parser error: ' . $e->getMessage();
            }
        }

        if (empty($errorDetail)) {
            $errorDetail = 'ระบบยังไม่ได้ติดตั้งส่วนเสริมสำหรับอ่านไฟล์ Excel บนเซิร์ฟเวอร์นี้ (กรุณาบันทึกเป็น .csv หรือติดตั้ง phpoffice/phpspreadsheet)';
        }

        return false;
    }

    /**
     * Built-in pure PHP parser for XLSX files using standard ZipArchive and SimpleXML
     */
    private function parseXlsxBasic($filePath)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return false;
        }

        // Read sharedStrings if exists
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            $xml = @simplexml_load_string($stringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // Read sheet1.xml
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return false;
        }

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return false;
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $colIndex = 0;
            foreach ($row->c as $cell) {
                $coord = (string)$cell['r'];
                $colLetter = preg_replace('/[0-9]/', '', $coord);
                $expectedColIndex = 0;
                for ($i = 0; $i < strlen($colLetter); $i++) {
                    $expectedColIndex = $expectedColIndex * 26 + (ord($colLetter[$i]) - 64);
                }
                $expectedColIndex -= 1;

                while ($colIndex < $expectedColIndex) {
                    $rowData[] = '';
                    $colIndex++;
                }

                $type = (string)$cell['t'];
                $val = (string)$cell->v;

                if ($type === 's') {
                    $val = isset($sharedStrings[(int)$val]) ? $sharedStrings[(int)$val] : $val;
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $val = (string)$cell->is->t;
                }

                $rowData[] = $val;
                $colIndex++;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    public function resetAllPins(Request $request)
    {
        // ===== [แก้ไข ระดับ 1 ข้อ 3] สุ่ม PIN ใหม่ทั้งชุดแบบไม่ซ้ำกันเลย =====
        // เดิม: วนสุ่มด้วย rand() ทีละคนโดยไม่ตรวจซ้ำ ยิ่งคนเยอะยิ่งมี PIN ชนกัน
        // ใหม่: สุ่มสลับเลข 0000–9999 แล้วหยิบตามจำนวนคน จึงไม่ซ้ำแน่นอน
        DB::transaction(function () {
            $staffIds = Staff::lockForUpdate()->pluck('staff_id')->values();

            if ($staffIds->count() > 10000) {
                throw new \RuntimeException('จำนวนบุคลากรเกินจำนวน PIN 4 หลักที่เป็นไปได้');
            }

            $pins = collect(range(0, 9999))->shuffle()->take($staffIds->count())->values();

            // ใส่ค่าชั่วคราวที่ไม่ซ้ำก่อน เพื่อไม่ให้ชน unique index ระหว่างสลับ PIN
            Staff::query()->update(['staff_pincode' => DB::raw("CONCAT('tmp-', staff_id)")]);

            foreach ($staffIds as $i => $staffId) {
                Staff::where('staff_id', $staffId)->update([
                    'staff_pincode' => str_pad((string) $pins[$i], 4, '0', STR_PAD_LEFT),
                ]);
            }
        });

        // เดิม: 'Reset PIN Code ทั้งหมดสำเร็จ' — แต่ระบบยังไม่ได้ส่งอีเมล แจ้งให้แอดมินรู้ตามจริง
        return redirect()->route('staff.index')->with('success', 'Reset PIN Code ทั้งหมดสำเร็จ — ระบบยังไม่ได้ส่ง PIN ทางอีเมล กรุณาแจ้ง PIN ใหม่ให้บุคลากร (ผู้ใช้ระดับ Executive ดู PIN ได้ในหน้าบุคลากร)');
        // ===== [จบ ข้อ 3: resetAllPins] =====
    }

    /**
     * Admin รีเซ็ตรหัสผ่านให้บุคลากร
     */
    public function resetPassword(Request $request, $id)
    {
        $staff = Staff::findOrFail($id);

        // [สิทธิ์ตามบทบาท] รีเซ็ตรหัสผ่านของ Executive ได้เฉพาะ Executive
        // (ไม่งั้น Admin รีเซ็ตรหัสผ่าน Executive แล้วล็อกอินแทนเพื่อดู PIN ได้)
        if ($staff->isExecutive() && !auth()->user()->isExecutive()) {
            $message = 'เฉพาะ Executive เท่านั้นที่รีเซ็ตรหัสผ่านของ Executive ได้';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            return redirect()->route('staff.index')->withErrors([$message]);
        }

        if ($request->reset_type === 'custom') {
            $request->validate([
                'new_password' => 'required|string|min:6',
            ], [
                'new_password.required' => 'กรุณากรอกรหัสผ่านใหม่',
                'new_password.min' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร',
            ]);
            $newPassword = $request->new_password;
        } else {
            $newPassword = '123456';
        }

        $staff->password = Hash::make($newPassword);
        $staff->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "รีเซ็ตรหัสผ่านของ {$staff->staff_name} เป็น \"{$newPassword}\" เรียบร้อยแล้ว",
            ]);
        }

        return redirect()->route('staff.index')
            ->with('success', "รีเซ็ตรหัสผ่านของ {$staff->staff_name} เป็น \"{$newPassword}\" สำเร็จ");
    }

    /**
     * staff_id ที่มี session login ยังไม่หมดอายุ (จากตาราง sessions)
     *
     * @return \Illuminate\Support\Collection<int, int> keys เป็น staff_id สำหรับ isset lookup
     */
    private function loggedInStaffIdSet()
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        $minLastActivity = time() - ((int) config('session.lifetime') * 60);

        return DB::table(config('session.table', 'sessions'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $minLastActivity)
            ->distinct()
            ->pluck('user_id')
            ->flip();
    }
}