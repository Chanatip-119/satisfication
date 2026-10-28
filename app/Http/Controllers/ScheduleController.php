<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Counter;
use App\Models\Staff;
use App\Models\Evaluation; // [แก้ไข ระดับ 2 ข้อ 10] ใช้นับคะแนนประเมินตอนแจ้งว่าลบ Slot ไม่ได้
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $staffs = Staff::all();
        $weekOffset = (int) $request->query('week_offset', 0);
        $startOfWeek = Carbon::now()->addWeeks($weekOffset)->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();
        
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekDays[] = $startOfWeek->copy()->addDays($i);
        }

        $weekLabel = $startOfWeek->translatedFormat('j') . ' - ' . $endOfWeek->translatedFormat('j F Y');

        $counters = Counter::with(['countersubs.schedules' => function ($query) use ($startOfWeek, $endOfWeek) {
            // [แก้ไข ระดับ 2 ข้อ 10] นับจำนวน Check-in ของแต่ละกะ เพื่อให้หน้าเว็บรู้ว่ากะไหนลบไม่ได้
            $query->with('staff')
                  ->withCount('checkins')
                  ->whereBetween('schedule_date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
                  ->orderBy('start_time', 'asc')
                  ->orderBy('schedule_id', 'asc'); 
        }])->where('is_active', true)->get();

        // ตรวจสอบเคาน์เตอร์เดี่ยว (เช่น 4, 5 หรือเคาน์เตอร์ที่ยังไม่มีจุดย่อย) ให้มี CounterSub เริ่มต้นเสมอ
        foreach ($counters as $c) {
            if ($c->countersubs->isEmpty()) {
                $defaultSub = \App\Models\CounterSub::firstOrCreate(
                    ['counter_id' => $c->counter_id],
                    ['is_active' => true]
                );
                $defaultSub->setRelation('schedules', collect([]));
                $c->setRelation('countersubs', collect([$defaultSub]));
            }
        }

        // สร้าง Map จับคู่ counter_sub_id กับ counter_id เพื่อใช้แสดงชื่อเคาน์เตอร์ตอนเกิด Conflict
        $subToCounterMap = [];
        foreach ($counters as $c) {
            foreach ($c->countersubs as $cs) {
                $subToCounterMap[$cs->counter_sub_id] = $c->counter_id;
            }
        }

        $allSchedulesForOverlap = Schedule::all(); 

        // เตรียมข้อมูลตารางงานทั้งหมดของบุคลากรแต่ละคน พร้อมเลขเคาน์เตอร์ เพื่อส่งให้ JS ตรวจ Conflict
        $staffAvailability = $staffs->map(function($staff) use ($allSchedulesForOverlap, $subToCounterMap) {
            return [
                'id' => $staff->staff_id,
                'name' => $staff->staff_name,
                'schedules' => $allSchedulesForOverlap->where('staff_id', $staff->staff_id)
                    ->map(function($s) use ($subToCounterMap) {
                        return [
                            'schedule_id'    => $s->schedule_id,
                            'date'           => $s->schedule_date,
                            'start'          => substr($s->start_time, 0, 5),
                            'end'            => substr($s->end_time, 0, 5),
                            'counter_sub_id' => $s->counter_sub_id,
                            'counter_id'     => $subToCounterMap[$s->counter_sub_id] ?? $s->counter_sub_id
                        ];
                    })->values()->toArray()
            ];
        });

        $schedules = $allSchedulesForOverlap;
        return view('schedules.index', compact('counters', 'staffs', 'weekDays', 'weekLabel', 'weekOffset', 'schedules', 'staffAvailability'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'counter_sub_id' => 'nullable|integer', 
            'staff_id'       => 'required|integer', 
            'schedule_date'  => 'required|date',   
            'end_date'       => 'nullable|date|after_or_equal:schedule_date',
            'start_time'     => 'required',        
            'end_time'       => 'required|after:start_time',
            'status'         => 'nullable|string',
            'work_days'      => 'nullable|array',
        ]);

        $startDate = Carbon::parse($request->schedule_date);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy();
        $selectedDays = $request->work_days ?? [$startDate->dayOfWeek]; 

        // 1. ตรวจสอบ Conflict ในระดับ Backend ก่อนบันทึกจริง
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (in_array($date->dayOfWeek, $selectedDays)) {
                $dateStr = $date->format('Y-m-d');

                $conflict = Schedule::with('staff')
                    ->where('staff_id', $request->staff_id)
                    ->where('schedule_date', $dateStr)
                    ->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    })->first();

                if ($conflict) {
                    $staffName = $conflict->staff->staff_name ?? 'บุคลากรนี้';
                    $cTime = substr($conflict->start_time, 0, 5) . '–' . substr($conflict->end_time, 0, 5);
                    return redirect()->back()->withErrors(["Conflict! {$staffName} มี slot ช่วงเวลาเดียวกัน ({$cTime}) ในวันที่ {$dateStr} — กรุณาเปลี่ยนเวลาหรือเลือกคนอื่น"]);
                }
            }
        }

        // 2. บันทึกข้อมูลด้วย Transaction เมื่อไม่พบตารางชน
        return DB::transaction(function () use ($request, $startDate, $endDate, $selectedDays) {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if (in_array($date->dayOfWeek, $selectedDays)) {
                    $newSchedule = new Schedule();
                    if ($request->has('counter_sub_id')) { $newSchedule->counter_sub_id = $request->counter_sub_id; }
                    $newSchedule->staff_id      = $request->staff_id;
                    $newSchedule->schedule_date = $date->format('Y-m-d');
                    $newSchedule->start_time    = $request->start_time;
                    $newSchedule->end_time      = $request->end_time;
                    $newSchedule->status        = $request->status ?? 'empty'; 
                    $newSchedule->save();
                }
            }
            return redirect()->back()->with('success', 'เพิ่มตารางปฏิบัติงานเรียบร้อยแล้วครับ');
        });
    }

    public function update(Request $request, $schedule_id)
    {
        $request->validate([
            'staff_id'      => 'required|integer',
            'schedule_date' => 'required|date',
            'start_time'    => 'required',
            'end_time'      => 'required|after:start_time',
            'status'        => 'nullable|string'
        ]);

        // ตรวจสอบว่าเวลาที่แก้ไขชนกับตารางอื่นของบุคลากรคนเดียวกันหรือไม่
        $conflict = Schedule::with('staff')
            ->where('staff_id', $request->staff_id)
            ->where('schedule_date', $request->schedule_date)
            ->where('schedule_id', '!=', $schedule_id)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);
            })->first();

        if ($conflict) {
            $staffName = $conflict->staff->staff_name ?? 'บุคลากรนี้';
            $cTime = substr($conflict->start_time, 0, 5) . '–' . substr($conflict->end_time, 0, 5);
            return redirect()->back()->withErrors(["Conflict! {$staffName} มี slot ช่วงเวลาเดียวกัน ({$cTime}) อยู่แล้ว ไม่สามารถแก้ไขเวลาทับซ้อนได้"]);
        }

        return DB::transaction(function () use ($request, $schedule_id) {
            $schedule = Schedule::findOrFail($schedule_id);
            $schedule->staff_id      = $request->staff_id;
            $schedule->schedule_date = $request->schedule_date;
            $schedule->start_time    = $request->start_time;
            $schedule->end_time      = $request->end_time;
            if ($request->has('status')) { $schedule->status = $request->status; }
            $schedule->save();

            return redirect()->back()->with('success', 'แก้ไขข้อมูลตารางเรียบร้อยแล้วครับ');
        });
    }

    public function destroy($schedule_id)
    {
        return DB::transaction(function () use ($schedule_id) {
            $schedule = Schedule::withCount('checkins')->lockForUpdate()->findOrFail($schedule_id);

            // ===== [แก้ไข ระดับ 2 ข้อ 10] ห้ามลบกะที่มีประวัติ Check-in แล้ว =====
            // เดิม: $schedule->delete(); ทันที
            // ปัญหา: ตาราง checkin ผูก schedule_id ไว้โดยไม่มี cascade ถ้ากะเคยมีคน Check-in
            //        Database จะไม่ยอมให้ลบ และหน้าเว็บขึ้น error 500
            // ใหม่: แจ้งเหตุผลชัดเจน และเก็บประวัติ Check-in กับคะแนนประเมินไว้ (ไม่ลบตามไปด้วย)
            if ($schedule->checkins_count > 0) {
                $evaluationCount = Evaluation::whereIn('checkin_id', $schedule->checkins()->pluck('checkin_id'))->count();

                $message = "ไม่สามารถลบ Slot นี้ได้ เพราะมีประวัติ Check-in {$schedule->checkins_count} ครั้ง";
                if ($evaluationCount > 0) {
                    $message .= " และคะแนนประเมิน {$evaluationCount} รายการผูกอยู่";
                }
                $message .= " — หากต้องการเปลี่ยนแปลง ให้แก้ไขเวลาแทนการลบ เพื่อเก็บประวัติไว้";

                return redirect()->back()->withErrors([$message]);
            }
            // ===== [จบ ข้อ 10] =====

            $schedule->delete();
            return redirect()->back()->with('success', 'ลบข้อมูลตารางปฏิบัติงานเรียบร้อยแล้ว');
        });
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|max:10240'
        ], [
            'import_file.required' => 'กรุณาเลือกไฟล์ที่ต้องการนำเข้า',
            'import_file.file' => 'ไฟล์ที่อัปโหลดไม่ถูกต้อง',
            'import_file.max' => 'ขนาดไฟล์ต้องไม่เกิน 10MB'
        ]);

        $file = $request->file('import_file');
        $extension = strtolower(trim($file->getClientOriginalExtension()));

        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'])) {
            return redirect()->back()->withErrors(['รูปแบบไฟล์ไม่ถูกต้อง รองรับเฉพาะไฟล์นามสกุล .xlsx, .xls, .csv']);
        }

        $rows = [];
        $errorDetail = '';

        if (in_array($extension, ['csv', 'txt'])) {
            $rows = $this->parseCsv($file->getRealPath());
        } elseif (in_array($extension, ['xlsx', 'xls', 'xlsm'])) {
            $rows = $this->parseXlsx($file, $errorDetail);
            if ($rows === false) {
                return redirect()->back()->withErrors([
                    'ไม่สามารถอ่านไฟล์ Excel ได้' . ($errorDetail ? ": {$errorDetail}" : ' โปรดลองบันทึกเป็น .csv แล้วลองใหม่อีกครั้ง')
                ]);
            }
        }

        if (empty($rows)) {
            return redirect()->back()->withErrors(['ไม่พบข้อมูลในไฟล์']);
        }

        // ข้ามแถว Header (แถวแรก)
        if (count($rows) > 0) {
            array_shift($rows);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // ดึงรายชื่อ counter_sub_id และ staff_id ที่มีอยู่จริงในระบบ
        $validCounterSubIds = \App\Models\CounterSub::pluck('counter_sub_id')->toArray();
        $validStaffIds = Staff::pluck('staff_id')->toArray();

        return DB::transaction(function () use ($rows, &$imported, &$skipped, &$errors, $validCounterSubIds, $validStaffIds) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // +2 เพราะ index เริ่มที่ 0 และข้าม header ไปแล้ว 1 แถว

                $counterSubRaw = isset($row[0]) ? trim((string)$row[0]) : '';
                $staffRaw      = isset($row[1]) ? trim((string)$row[1]) : '';
                $dateRaw       = isset($row[2]) ? trim((string)$row[2]) : '';
                $startTimeRaw  = isset($row[3]) ? trim((string)$row[3]) : '';
                $endTimeRaw    = isset($row[4]) ? trim((string)$row[4]) : '';

                // ข้ามแถวว่าง
                if (empty($counterSubRaw) && empty($staffRaw) && empty($dateRaw)) {
                    continue;
                }

                // ตรวจสอบข้อมูลเบื้องต้น
                if (empty($counterSubRaw)) {
                    $errors[] = "แถวที่ {$rowNum}: ไม่มีรหัสเคาน์เตอร์ย่อย (คอลัมน์ A)";
                    $skipped++;
                    continue;
                }
                if (empty($staffRaw)) {
                    $errors[] = "แถวที่ {$rowNum}: ไม่มีรหัสหรือชื่อบุคลากร (คอลัมน์ B)";
                    $skipped++;
                    continue;
                }
                if (empty($dateRaw)) {
                    $errors[] = "แถวที่ {$rowNum}: ไม่มีวันที่ (คอลัมน์ C)";
                    $skipped++;
                    continue;
                }
                if (empty($startTimeRaw) || empty($endTimeRaw)) {
                    $errors[] = "แถวที่ {$rowNum}: ไม่มีเวลาเริ่ม/สิ้นสุด (คอลัมน์ D, E)";
                    $skipped++;
                    continue;
                }

                // 1. ตรวจสอบและแปลง Counter Sub ID (รองรับทั้ง 1, 2 หรือ 1.1, 1.2)
                $counterSubId = null;
                if (is_numeric($counterSubRaw) && in_array((int)$counterSubRaw, $validCounterSubIds)) {
                    $counterSubId = (int)$counterSubRaw;
                } elseif (str_contains($counterSubRaw, '.')) {
                    // หากใส่ในรูป 1.2 หรือ เคาน์เตอร์ 1.2
                    $cleanDot = preg_replace('/[^0-9\.]/', '', $counterSubRaw);
                    $parts = explode('.', $cleanDot);
                    $subId = isset($parts[1]) ? (int)$parts[1] : (int)$parts[0];
                    if (in_array($subId, $validCounterSubIds)) {
                        $counterSubId = $subId;
                    }
                }

                if (!$counterSubId) {
                    $cleanedNumeric = (int) preg_replace('/[^0-9]/', '', $counterSubRaw);
                    if ($cleanedNumeric > 0 && in_array($cleanedNumeric, $validCounterSubIds)) {
                        $counterSubId = $cleanedNumeric;
                    }
                }

                if (!$counterSubId) {
                    $errors[] = "แถวที่ {$rowNum}: ไม่พบเคาน์เตอร์ย่อย \"{$counterSubRaw}\" ในระบบ";
                    $skipped++;
                    continue;
                }

                // 2. ตรวจสอบและแปลง Staff (รองรับตัวเลข 10, รหัส BUU-010, หรือชื่อพนักงาน)
                $staffId = null;
                $numericStaff = (int) preg_replace('/[^0-9]/', '', $staffRaw);
                if ($numericStaff > 0 && in_array($numericStaff, $validStaffIds)) {
                    $staffId = $numericStaff;
                } else {
                    // ค้นหาตามชื่อพนักงานในกรณีใส่ชื่อมาแทนรหัส
                    $foundStaff = Staff::where('staff_name', 'LIKE', '%' . $staffRaw . '%')->first();
                    if ($foundStaff) {
                        $staffId = $foundStaff->staff_id;
                    }
                }

                if (!$staffId) {
                    $errors[] = "แถวที่ {$rowNum}: ไม่พบบุคลากร \"{$staffRaw}\" ในระบบ";
                    $skipped++;
                    continue;
                }

                // 3. แปลงวันที่
                try {
                    $scheduleDate = $this->parseDate($dateRaw);
                } catch (\Exception $e) {
                    $errors[] = "แถวที่ {$rowNum}: วันที่ไม่ถูกต้อง \"{$dateRaw}\"";
                    $skipped++;
                    continue;
                }

                // 4. แปลงเวลา
                try {
                    $startTime = $this->parseTime($startTimeRaw);
                    $endTime   = $this->parseTime($endTimeRaw);
                } catch (\Exception $e) {
                    $errors[] = "แถวที่ {$rowNum}: เวลาไม่ถูกต้อง (\"{$startTimeRaw}\" - \"{$endTimeRaw}\")";
                    $skipped++;
                    continue;
                }

                // ตรวจสอบเวลาเริ่มต้นต้องน้อยกว่าเวลาสิ้นสุด
                if ($startTime >= $endTime) {
                    $errors[] = "แถวที่ {$rowNum}: เวลาเริ่ม ({$startTime}) ต้องน้อยกว่าเวลาสิ้นสุด ({$endTime})";
                    $skipped++;
                    continue;
                }

                // 5. ตรวจสอบตารางซ้ำ (Duplicate check)
                $existing = Schedule::where('counter_sub_id', $counterSubId)
                    ->where('staff_id', $staffId)
                    ->where('schedule_date', $scheduleDate)
                    ->where('start_time', $startTime)
                    ->where('end_time', $endTime)
                    ->first();

                if ($existing) {
                    $errors[] = "แถวที่ {$rowNum}: ตารางเวรนี้มีอยู่ในระบบแล้ว (ข้าม)";
                    $skipped++;
                    continue;
                }

                try {
                    $newSchedule = new Schedule();
                    $newSchedule->counter_sub_id = $counterSubId;
                    $newSchedule->staff_id       = $staffId;
                    $newSchedule->schedule_date  = $scheduleDate;
                    $newSchedule->start_time     = $startTime;
                    $newSchedule->end_time       = $endTime;
                    $newSchedule->status         = 'empty';
                    $newSchedule->save();
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "แถวที่ {$rowNum}: บันทึกไม่สำเร็จ — " . $e->getMessage();
                    $skipped++;
                }
            }

            $message = "Import สำเร็จ: นำเข้าตาราง {$imported} รายการ";
            if ($skipped > 0) {
                $message .= ", ข้าม {$skipped} รายการ";
            }

            if ($imported > 0) {
                $redirect = redirect()->back()->with('success', $message);
                if (!empty($errors)) {
                    $redirect->with('import_errors', $errors);
                }
                return $redirect;
            } else {
                $redirect = redirect()->back()->withErrors(['ไม่พบข้อมูลที่สามารถนำเข้าได้ กรุณาตรวจสอบรายละเอียดด้านล่าง']);
                if (!empty($errors)) {
                    $redirect->with('import_errors', $errors);
                }
                return $redirect;
            }
        });
    }

    /**
     * Download schedule import template CSV
     */
    public function downloadTemplate()
    {
        $filename = 'schedule_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM สำหรับเปิดในโปรแกรม Excel ภาษาไทยได้ทันที
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($file, [
                'counter_sub_id',
                'staff_id',
                'schedule_date',
                'start_time',
                'end_time',
            ]);

            // Example rows
            fputcsv($file, ['1', '10', '2026-10-20', '08:30', '12:00']);
            fputcsv($file, ['1', '11', '2026-10-20', '13:00', '16:30']);
            fputcsv($file, ['2', '10', '2026-10-21', '08:30', '12:00']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
     * Try to parse XLSX/XLS file using PhpSpreadsheet or built-in ZipArchive parser
     */
    private function parseXlsx($file, &$errorDetail = '')
    {
        // 1. ลองใช้ PhpSpreadsheet ก่อน
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
                        $val = $cell->getValue();
                        // กรณีเป็นวันที่หรือเวลาในระบบ Excel
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            try {
                                $dateObj = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                                if (is_numeric($val) && (float)$val >= 0 && (float)$val < 1) {
                                    $val = $dateObj->format('H:i');
                                } else {
                                    $val = $dateObj->format('Y-m-d');
                                }
                            } catch (\Throwable $te) {
                                // fallback to raw
                            }
                        }
                        $rowData[] = $val;
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
            $errorDetail = 'ระบบยังไม่ได้ติดตั้งส่วนเสริมสำหรับอ่านไฟล์ Excel บนเซิร์ฟเวอร์นี้ (กรุณาเปิด extension=zip หรือติดตั้ง phpoffice/phpspreadsheet)';
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

    /**
     * Parse date value — supports text dates (YYYY-MM-DD, DD/MM/YYYY, พ.ศ.) and Excel serial numbers
     */
    private function parseDate($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string)$value);

        // Excel serial number (e.g. 46320 = วันที่ใน Excel)
        if (is_numeric($value) && (int)$value > 30000 && (int)$value < 80000) {
            $unixTimestamp = ($value - 25569) * 86400;
            return Carbon::createFromTimestamp($unixTimestamp)->format('Y-m-d');
        }

        // รูปแบบ DD/MM/YYYY หรือ DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];
            
            // หากปีเป็น พ.ศ. (> 2500) ให้แปลงเป็น ค.ศ.
            if ($year > 2500) {
                $year -= 543;
            }
            
            return Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
        }

        // รูปแบบ YYYY-MM-DD โดยปีอาจเป็น พ.ศ.
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];

            if ($year > 2500) {
                $year -= 543;
            }

            return Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
        }

        // รูปแบบมาตรฐานสากล
        return Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Parse time value — รองรับ HH:MM, H:MM, HH:MM:SS, จุดทศนิยม (8.30) และ Excel fraction
     */
    private function parseTime($value)
    {
        $value = trim((string)$value);
        // ตัดคำว่า "น." หรือ "น" ที่อาจพิมพ์ต่อท้ายเวลา
        $value = preg_replace('/\s*น\.?$/u', '', $value);

        // Excel fraction (0.0 - 1.0)
        if (is_numeric($value) && (float)$value >= 0 && (float)$value < 1) {
            $totalMinutes = round((float)$value * 24 * 60);
            $hours = intdiv((int)$totalMinutes, 60);
            $minutes = (int)$totalMinutes % 60;
            return sprintf('%02d:%02d', $hours, $minutes);
        }

        // กรณีเขียนแบบจุด เช่น 08.30 หรือ 8.30
        if (preg_match('/^(\d{1,2})\.(\d{2})$/', $value, $matches)) {
            return sprintf('%02d:%02d', (int)$matches[1], (int)$matches[2]);
        }

        // รูปแบบมาตรฐาน HH:MM หรือ HH:MM:SS
        if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $value, $matches)) {
            return sprintf('%02d:%02d', (int)$matches[1], (int)$matches[2]);
        }

        // กรณีเป็นตัวเลขชั่วโมงล้วนๆ เช่น 8, 12, 13 (เช่น เมื่อ Excel แปลง 13.00 เป็นจำนวนเต็ม 13)
        if (preg_match('/^(\d{1,2})$/', $value, $matches)) {
            $hour = (int)$matches[1];
            if ($hour >= 0 && $hour <= 24) {
                return sprintf('%02d:00', $hour);
            }
        }

        throw new \Exception("ไม่สามารถแปลงเวลา \"{$value}\" ได้");
    }
}