<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\Counter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportReportController extends Controller
{
    public function index()
    {
        $counters = Counter::where('is_active', true)->get();
        return view('report.export', compact('counters'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'export_type' => 'required|in:pdf,excel',
            // [แก้ไข ระดับ 3 ข้อ 15] รับเฉพาะช่วงเวลาที่มีในหน้าเว็บ
            // เดิม: 'required' — ส่งค่าอื่นมาจะไม่เข้า case ใดเลย แล้วระบบส่งออกข้อมูล "ทั้งหมด" โดยไม่ตั้งใจ
            'time_range' => 'required|in:today,week,month,custom',
        ]);

        return DB::transaction(function () use ($request) {
            $startDate = null;
            $endDate = null;

            switch ($request->time_range) {
                case 'today':
                    $startDate = Carbon::today()->startOfDay();
                    $endDate = Carbon::today()->endOfDay();
                    break;
                case 'week':
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
                    break;
                case 'month':
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    break;
                case 'custom':
                    $request->validate([
                        'start_date' => 'required|date',
                        'end_date' => 'required|date|after_or_equal:start_date',
                    ]);
                    $startDate = Carbon::parse($request->start_date)->startOfDay();
                    $endDate = Carbon::parse($request->end_date)->endOfDay();
                    break;
            }

            $selectedCounters = [];
            if (!$request->has('select_all') && $request->has('counters')) {
                $selectedCounters = $request->counters;
            }

            // [แก้ไข ระดับ 3 ข้อ 15] โหลด counter มาด้วย เพื่อแสดงชื่อเคาน์เตอร์ที่อ่านเข้าใจได้
            // เดิม: Evaluation::with(['checkin.staff', 'checkin.schedule.counterSub']);
            $query = Evaluation::with(['checkin.staff', 'checkin.schedule.counterSub.counter']);

            if ($startDate && $endDate) {
                $query->whereBetween('evaluation_at', [$startDate, $endDate]);
            }

            if (!empty($selectedCounters)) {
                $query->whereHas('checkin.schedule.counterSub', function ($q) use ($selectedCounters) {
                    $q->whereIn('counter_id', $selectedCounters);
                });
            }

            $evaluations = $query->orderBy('evaluation_at', 'desc')->get();

            // ===== [แก้ไข ระดับ 3 ข้อ 15] เตรียมข้อมูลแต่ละแถวไว้ที่เดียว ใช้ร่วมกันทั้ง PDF และ CSV =====
            // เดิม: PDF แสดงคอลัมน์เคาน์เตอร์เป็นแค่เลข counter_sub_id (เช่น "7") และ CSV เป็น "เคาน์เตอร์ 7"
            //       ซึ่งไม่ตรงกับชื่อที่ใช้ในหน้าอื่น คนอ่านรายงานจะไม่รู้ว่าคือจุดไหน
            $rows = $evaluations->map(function ($eval) {
                $evaluatedAt = Carbon::parse($eval->evaluation_at);
                return [
                    'date'    => $evaluatedAt->format('d/m/Y'),
                    'time'    => $evaluatedAt->format('H:i'),
                    'counter' => $this->counterLabel($eval),
                    'staff'   => $eval->checkin && $eval->checkin->staff ? $eval->checkin->staff->staff_name : 'ไม่ระบุ',
                    'rating'  => (int) $eval->rating,
                    'comment' => (string) ($eval->comment ?? ''),
                ];
            });
            // ===== [จบ ข้อ 15: เตรียมข้อมูล] =====

            if ($request->export_type === 'pdf') {
                return $this->exportPDF($rows, $startDate, $endDate);
            } else {
                return $this->exportExcel($rows);
            }
        });
    }

    // [แก้ไข ระดับ 3 ข้อ 15] รับ $rows ที่เตรียมไว้แล้ว แทน $evaluations
    private function exportPDF($rows, $startDate, $endDate)
    {
        $dateLabel = ($startDate && $endDate) 
            ? $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y') 
            : 'ทั้งหมด';

        // [แก้ไข ระดับ 3 ข้อ 15] กำหนดขนาดกระดาษ A4 ชัดเจน (ฟอนต์ไทยตั้งค่าไว้ใน export_pdf_template)
        $pdf = Pdf::loadView('report.export_pdf_template', compact('rows', 'dateLabel'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('report_' . date('Ymd_His') . '.pdf');
    }

    // [แก้ไข ระดับ 3 ข้อ 15] รับ $rows ที่เตรียมไว้แล้ว แทน $evaluations
    private function exportExcel($rows)
    {
        $fileName = 'report_' . date('Ymd_His') . '.csv';
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            
            fputcsv($file, ['วันที่', 'เวลา', 'เคาน์เตอร์', 'รายชื่อ', 'คะแนน', 'ความคิดเห็น']);

            foreach ($rows as $row) {
                // [เพิ่มเติม ระดับ 3] กัน CSV Injection ทุกช่องที่เป็นข้อความ
                fputcsv($file, [
                    $row['date'],
                    $row['time'],
                    $this->csvSafe($row['counter']),
                    $this->csvSafe($row['staff']),
                    $row['rating'],
                    $this->csvSafe($row['comment']),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ===== [เพิ่มใหม่ ระดับ 3 ข้อ 15] ชื่อเคาน์เตอร์ที่อ่านเข้าใจได้ =====
    // ผลลัพธ์ เช่น "เคาน์เตอร์ 8 — บริการเคาน์เตอร์ชั้น 6"
    private function counterLabel($eval): string
    {
        $schedule = $eval->checkin?->schedule;
        if (!$schedule) {
            return 'ไม่ระบุ';
        }

        $counter = $schedule->counterSub?->counter;
        if (!$counter) {
            return 'จุดบริการ ID ' . $schedule->counter_sub_id;
        }

        return 'เคาน์เตอร์ ' . $counter->counter_id . ' — ' . $counter->counter_location;
    }
    // ===== [จบ ข้อ 15: counterLabel] =====

    // ===== [เพิ่มใหม่ ระดับ 3 — แก้เพิ่มจากที่หลุดในระดับ 1] กัน CSV Injection =====
    // ความคิดเห็นมาจากผู้รับบริการที่ไม่ต้องล็อกอิน ถ้าขึ้นต้นด้วย = + - @ เมื่อเปิดใน Excel จะถูกตีความเป็นสูตรและทำงานทันที
    // เติม ' นำหน้า เพื่อให้ Excel แสดงเป็นข้อความธรรมดา
    private function csvSafe($value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'" . $value : $value;
    }
    // ===== [จบ CSV Injection] =====
}