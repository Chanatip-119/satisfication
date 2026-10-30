<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use App\Services\CheckinService;
use App\Services\EvaluationExcelExporter;
use App\Services\EvaluationReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * [รายงาน+Online ข้อ 2/3] ส่งออกรายงาน — เขียนใหม่ทั้งไฟล์
 *
 * เดิม: PDF/CSV มีแค่รายการประเมินทีละแถว
 * ใหม่: ข้อมูลทั้งหมดมาจาก EvaluationReportService (คำนวณครั้งเดียว ตัวเลขใน PDF/Excel ตรงกัน)
 *   - PDF  : รายละเอียดเคาน์เตอร์, สรุปรายบุคลากร × เคาน์เตอร์, สรุปรายวัน × เคาน์เตอร์,
 *            รอบ Check-in (เวลาเริ่ม–สิ้นสุด), รายการประเมิน + คำชี้แจง
 *   - Excel: ไฟล์ .xlsx เลือกรูปแบบได้ (รายเคาน์เตอร์ / ภาพรวม / ค่าเฉลี่ย) แต่ละแบบเป็นชีตแยก
 *
 * คงไว้จากระดับก่อน: [สิทธิ์ตามบทบาท] Employee เข้าไม่ได้, [ระดับ 3 ข้อ 15] ช่วงเวลาต้องเป็นค่าที่กำหนด,
 *                   กัน Excel/CSV Injection (ตอนนี้อยู่ใน EvaluationExcelExporter)
 */
class ExportReportController extends Controller
{
    /** ช่วงเวลาที่กำหนดเองยาวได้สูงสุด (กันการสร้างรายงานใหญ่เกินจนเซิร์ฟเวอร์ค้าง) */
    private const MAX_CUSTOM_DAYS = 366;

    public function index()
    {
        // [สิทธิ์ตามบทบาท] Employee ส่งออกรายงานไม่ได้ (route กันด้วย can:manage-admin อยู่แล้ว ตรวจซ้ำอีกชั้น)
        abort_if(auth()->user()?->isEmployee(), 403);

        // [รายงาน+Online] แสดงทุกเคาน์เตอร์ (รวมที่ปิดใช้งาน เผื่อดูข้อมูลย้อนหลัง) เรียงตามเลข
        // เดิม: เฉพาะเคาน์เตอร์ที่เปิดใช้งาน — ปิดเคาน์เตอร์แล้วจะส่งออกข้อมูลย้อนหลังของเคาน์เตอร์นั้นไม่ได้
        $counters = Counter::orderBy('counter_id')->get();

        return view('report.export', [
            'counters'       => $counters,
            'excelAvailable' => EvaluationExcelExporter::isAvailable(),
        ]);
    }

    public function export(Request $request)
    {
        abort_if(auth()->user()?->isEmployee(), 403);

        $validated = $request->validate([
            'export_type'   => 'required|in:pdf,excel',
            // [ระดับ 3 ข้อ 15] รับเฉพาะช่วงเวลาที่มีในหน้าเว็บ
            'time_range'    => 'required|in:today,week,month,custom',
            'start_date'    => 'required_if:time_range,custom|nullable|date',
            'end_date'      => 'required_if:time_range,custom|nullable|date|after_or_equal:start_date',
            'select_all'    => 'nullable',
            'counters'      => 'nullable|array',
            'counters.*'    => ['integer', Rule::exists('counter', 'counter_id')],
            // [รายงาน+Online ข้อ 3] รูปแบบ Excel (เลือกได้หลายแบบ)
            'excel_modes'   => 'required_if:export_type,excel|nullable|array',
            'excel_modes.*' => [Rule::in(EvaluationExcelExporter::MODES)],
        ], [
            'start_date.required_if' => 'กรุณาเลือกวันที่เริ่มต้น',
            'end_date.required_if'   => 'กรุณาเลือกวันที่สิ้นสุด',
            'end_date.after_or_equal'=> 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
            'excel_modes.required_if'=> 'กรุณาเลือกรูปแบบรายงาน Excel อย่างน้อย 1 แบบ',
            'counters.*.exists'      => 'มีเคาน์เตอร์ที่เลือกไม่อยู่ในระบบแล้ว กรุณารีเฟรชหน้า',
        ]);

        // ----- ช่วงเวลา (เวลาไทย) -----
        $now = CheckinService::now();
        switch ($validated['time_range']) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
            case 'month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            default: // custom
                $start = Carbon::parse($validated['start_date'], CheckinService::TZ)->startOfDay();
                $end = Carbon::parse($validated['end_date'], CheckinService::TZ)->endOfDay();
                if ($start->diffInDays($end, true) > self::MAX_CUSTOM_DAYS) {
                    return back()->withErrors(['ช่วงเวลาที่กำหนดเองต้องไม่เกิน ' . self::MAX_CUSTOM_DAYS . ' วัน'])->withInput();
                }
        }

        // ----- เคาน์เตอร์ -----
        $counterIds = [];
        if (!$request->boolean('select_all')) {
            $counterIds = array_values(array_unique(array_map('intval', $validated['counters'] ?? [])));
            if (empty($counterIds)) {
                return back()->withErrors(['กรุณาเลือกเคาน์เตอร์อย่างน้อย 1 เคาน์เตอร์ หรือเลือก "ทั้งหมด"'])->withInput();
            }
        }

        $report = (new EvaluationReportService($start, $end, $counterIds))->build();
        $rangeLabel = $this->rangeLabel($start, $end);
        $fileBase = 'report_' . $now->format('Ymd_His');

        if ($validated['export_type'] === 'pdf') {
            // ===== [แก้ PDF] ตรวจฟอนต์ภาษาไทยก่อนสร้าง PDF =====
            // เดิม: ถ้าไม่มีฟอนต์ DomPDF จะใช้ฟอนต์ตั้งต้นที่ไม่มีอักษรไทย แล้วได้ไฟล์ที่ภาษาไทยเป็น ??? ทั้งหมด
            //       โดยไม่บอกสาเหตุ — ตอนนี้แจ้งชัดเจนว่าขาดอะไร
            $fontProblem = $this->thaiFontProblem();
            if ($fontProblem !== null) {
                return back()->withErrors([$fontProblem])->withInput();
            }

            // ระบุตำแหน่งฟอนต์และขอบเขตไฟล์ที่ DomPDF อ่านได้ให้ชัดเจน (ไม่พึ่งค่าตั้งต้นของไลบรารี)
            Pdf::setOption([
                'fontDir'     => storage_path('fonts'),
                'fontCache'   => storage_path('fonts'),
                'chroot'      => base_path(),
                'defaultFont' => 'THSarabunNew',
            ]);
            // ===== [จบ แก้ PDF: ตรวจฟอนต์] =====

            return Pdf::loadView('report.export_pdf_template', [
                'report'     => $report,
                'rangeLabel' => $rangeLabel,
            ])
                // [รายงาน+Online ข้อ 2] แนวนอน เพื่อให้คอลัมน์เคาน์เตอร์หลายช่องพอดีหน้า
                ->setPaper('a4', 'landscape')
                ->download($fileBase . '.pdf');
        }

        // ----- Excel (.xlsx) -----
        if (!EvaluationExcelExporter::isAvailable()) {
            return back()->withErrors(['ระบบยังไม่ได้ติดตั้งส่วนสร้างไฟล์ Excel (phpoffice/phpspreadsheet) — กรุณาแจ้งผู้ดูแลระบบ'])->withInput();
        }

        // เรียงรูปแบบตามลำดับมาตรฐาน และตัดค่าซ้ำ
        $modes = array_values(array_intersect(EvaluationExcelExporter::MODES, $validated['excel_modes'] ?? []));

        return (new EvaluationExcelExporter())->download($report, $modes, $fileBase . '.xlsx', $rangeLabel);
    }

    /**
     * [แก้ PDF] ตรวจความพร้อมของฟอนต์ภาษาไทย คืนข้อความปัญหา (หรือ null ถ้าพร้อม)
     */
    private function thaiFontProblem(): ?string
    {
        $dir = storage_path('fonts');
        $missing = [];
        foreach (['THSarabunNew.ttf', 'THSarabunNew-Bold.ttf'] as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            // ไฟล์ต้องมีอยู่ และต้องเป็นไฟล์ฟอนต์จริง (กันกรณีดาวน์โหลดได้หน้าเว็บ HTML หรือไฟล์ว่าง)
            if (!is_file($path) || filesize($path) < 10240) {
                $missing[] = $file;
            }
        }

        if (!empty($missing)) {
            return 'ยังไม่พบไฟล์ฟอนต์ภาษาไทย: ' . implode(', ', $missing)
                . ' ในโฟลเดอร์ storage/fonts (ชื่อไฟล์ต้องตรงทุกตัวอักษร) — PDF จึงยังแสดงภาษาไทยไม่ได้';
        }
        if (!is_writable($dir)) {
            return 'ระบบเขียนโฟลเดอร์ storage/fonts ไม่ได้ (DomPDF ต้องสร้างไฟล์ cache ของฟอนต์) — กรุณาตั้งสิทธิ์ให้ www-data';
        }
        return null;
    }

    /** เช่น "1/10/2569 – 31/10/2569" หรือ "31/10/2569" */
    private function rangeLabel(Carbon $start, Carbon $end): string
    {
        $format = fn (Carbon $d) => $d->format('j/n/') . ($d->year + 543);
        return $start->isSameDay($end) ? $format($start) : $format($start) . ' – ' . $format($end);
    }
}