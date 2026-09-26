<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\Role;
use App\Models\Checkin;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class StaffController extends Controller
{
    public function index()
    {
        $staffs = Staff::with('role')->get();

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

        $filename = 'staff_export_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($staffs) {
            $file = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($staffs as $staff) {
                fputcsv($file, [
                    $staff->staff_id,
                    $staff->staff_name,
                    $staff->staff_email,
                    $staff->staff_pincode,
                    $staff->role_id,
                    $staff->role ? $staff->role->role_name : '',
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
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Example row
            fputcsv($file, [
                '10',
                'นายตัวอย่าง ทดสอบ',
                'example@go.buu.ac.th',
                '1234',
                '4',
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
            'excel_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $file = $request->file('excel_file');
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = [];

        if (in_array($extension, ['csv', 'txt'])) {
            $rows = $this->parseCsv($file->getRealPath());
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            // Try to parse xlsx as CSV (some xlsx can be parsed), otherwise show error
            $rows = $this->parseXlsx($file);
            if ($rows === false) {
                return redirect()->route('staff.index')->with('error', 'ไม่รองรับไฟล์ .xlsx/.xls โปรดบันทึกเป็น .csv แล้วลองใหม่');
            }
        } else {
            return redirect()->route('staff.index')->with('error', 'รูปแบบไฟล์ไม่ถูกต้อง รองรับเฉพาะ .csv');
        }

        if (empty($rows)) {
            return redirect()->route('staff.index')->with('error', 'ไม่พบข้อมูลในไฟล์');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1; // +1 because index starts at 0 and there is no header

            // Extract values by column position (0-indexed)
            $staffId = isset($row[0]) ? trim($row[0]) : '';
            $staffName = isset($row[1]) ? trim($row[1]) : '';
            $staffEmail = isset($row[2]) ? trim($row[2]) : '';
            $staffPincode = isset($row[3]) ? trim($row[3]) : '';
            $roleId = isset($row[4]) ? trim($row[4]) : '4';

            // Skip empty rows
            if (empty($staffId) && empty($staffName)) {
                continue;
            }

            // Extract numeric staff_id
            $staffIdNum = (int) preg_replace('/[^0-9]/', '', $staffId);

            if ($staffIdNum <= 0) {
                $errors[] = "แถวที่ {$rowNum}: รหัสพนักงานไม่ถูกต้อง";
                $skipped++;
                continue;
            }

            if (empty($staffName)) {
                $errors[] = "แถวที่ {$rowNum}: ไม่มีชื่อ-นามสกุล";
                $skipped++;
                continue;
            }

            // Check if staff_id already exists
            if (Staff::where('staff_id', $staffIdNum)->exists()) {
                $errors[] = "แถวที่ {$rowNum}: รหัสพนักงาน {$staffIdNum} มีอยู่แล้ว (ข้าม)";
                $skipped++;
                continue;
            }

            // Validate role_id
            $roleId = (int) $roleId;
            if (!in_array($roleId, [1, 2, 3, 4])) {
                $roleId = 4; // default to Employee
            }

            try {
                $staff = new Staff();
                $staff->staff_id = $staffIdNum;
                $staff->staff_name = $staffName;
                $staff->staff_email = $staffEmail ?: '';
                $staff->staff_pincode = $staffPincode ?: str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $staff->role_id = $roleId;
                $staff->save();
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "แถวที่ {$rowNum}: เกิดข้อผิดพลาด - " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "Import สำเร็จ: เพิ่ม {$imported} คน";
        if ($skipped > 0) {
            $message .= ", ข้าม {$skipped} รายการ";
        }

        if (!empty($errors)) {
            return redirect()->route('staff.index')
                ->with('success', $message)
                ->with('import_errors', $errors);
        }

        return redirect()->route('staff.index')->with('success', $message);
    }

    /**
     * Parse CSV file into array of rows
     */
    private function parseCsv($filepath)
    {
        $rows = [];
        $handle = fopen($filepath, 'r');

        if ($handle === false) {
            return $rows;
        }

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
            rewind($handle);
        }

        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = $data;
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Try to parse XLSX file. Returns array of rows or false if not supported.
     */
    private function parseXlsx($file)
    {
        // If PhpSpreadsheet is available, use it
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
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
                return $rows;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    public function resetAllPins(Request $request)
    {
        $staffs = Staff::all();
        foreach ($staffs as $staff) {
            $newPin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $staff->staff_pincode = $newPin;
            $staff->save();
        }
        return redirect()->route('staff.index')->with('success', 'Reset PIN Code ทั้งหมดสำเร็จ');
    }
}