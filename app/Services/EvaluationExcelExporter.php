<?php

namespace App\Services;

use App\Models\Evaluation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * [รายงาน+Online ข้อ 3] สร้างไฟล์ Excel (.xlsx) จากข้อมูลของ EvaluationReportService
 *
 * รูปแบบที่เลือกได้ (เลือกพร้อมกันได้หลายแบบ — แต่ละแบบเป็นชีตแยก):
 *   - average     ค่าเฉลี่ย  : สรุปรายบุคลากร × เคาน์เตอร์, สรุปรายวัน × เคาน์เตอร์, รายละเอียดเคาน์เตอร์
 *   - overall     ภาพรวม    : รายการประเมินทั้งหมด, รอบ Check-in ทั้งหมด
 *   - per_counter รายเคาน์เตอร์ : 1 ชีตต่อ 1 เคาน์เตอร์ (รายละเอียด + การกระจายคะแนน + รอบ Check-in + รายการประเมิน)
 *
 * ความปลอดภัย: ข้อความทุกช่อง (ชื่อ / ความคิดเห็น / คำชี้แจง) เขียนแบบ "ข้อความล้วน" (TYPE_STRING)
 * ความคิดเห็นที่ขึ้นต้นด้วย = จึงไม่กลายเป็นสูตร Excel (กัน CSV/Excel Injection)
 */
class EvaluationExcelExporter
{
    public const MODE_AVERAGE = 'average';
    public const MODE_OVERALL = 'overall';
    public const MODE_PER_COUNTER = 'per_counter';

    public const MODES = [self::MODE_PER_COUNTER, self::MODE_OVERALL, self::MODE_AVERAGE];

    /** สีพื้นตามระดับคะแนน — เกณฑ์เดียวกับ Evaluation::scoreClass() */
    private const SCORE_FILL = [
        'score-high' => 'E6FFFA',
        'score-mid'  => 'FEF3C7',
        'score-low'  => 'FEE2E2',
    ];

    private const HEADER_FILL = 'FFF3BF';
    private const TOTAL_FILL = 'EDF2F7';

    private array $usedTitles = [];

    public static function isAvailable(): bool
    {
        return class_exists(Spreadsheet::class);
    }

    /**
     * @param string[] $modes
     */
    public function download(array $report, array $modes, string $filename, string $rangeLabel)
    {
        $spreadsheet = $this->build($report, $modes, $rangeLabel);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store',
        ]);
    }

    public function build(array $report, array $modes, string $rangeLabel): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);
        $this->usedTitles = [];

        // ชีตแรก: ข้อมูลรายงาน (ใช้ชีตที่สร้างมาพร้อมไฟล์)
        $this->sheetCover($spreadsheet->getActiveSheet(), $report, $modes, $rangeLabel);

        if (in_array(self::MODE_AVERAGE, $modes, true)) {
            $this->sheetPivot($this->newSheet($spreadsheet, 'สรุปรายบุคลากร'), 'สรุปคะแนนรายบุคลากร × เคาน์เตอร์', 'บุคลากร', $report['by_staff'], $report['counters'], $rangeLabel);
            $this->sheetPivot($this->newSheet($spreadsheet, 'สรุปรายวัน'), 'สรุปคะแนนรายวัน × เคาน์เตอร์', 'วันที่', $report['by_date'], $report['counters'], $rangeLabel);
            $this->sheetCounterDetails($this->newSheet($spreadsheet, 'รายละเอียดเคาน์เตอร์'), $report['counter_details'], $rangeLabel);
        }

        if (in_array(self::MODE_OVERALL, $modes, true)) {
            $this->sheetEvaluations($this->newSheet($spreadsheet, 'รายการประเมินทั้งหมด'), 'รายการประเมินทั้งหมด', $report['evaluations'], $rangeLabel);
            $this->sheetSessions($this->newSheet($spreadsheet, 'รอบ Check-in'), 'รอบการ Check-in (เวลาเข้า–ออกจริง)', $report['sessions'], $rangeLabel);
        }

        if (in_array(self::MODE_PER_COUNTER, $modes, true)) {
            foreach ($report['counter_details'] as $detail) {
                $this->sheetSingleCounter($this->newSheet($spreadsheet, $detail['label']), $detail, $rangeLabel);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    // ------------------------------------------------------------------
    // ชีตต่าง ๆ
    // ------------------------------------------------------------------

    private function sheetCover(Worksheet $sheet, array $report, array $modes, string $rangeLabel): void
    {
        $sheet->setTitle($this->safeTitle('ข้อมูลรายงาน'));
        $this->title($sheet, 1, 'รายงานผลการประเมินความพึงพอใจ', 2);

        $modeLabels = [
            self::MODE_PER_COUNTER => 'รายเคาน์เตอร์',
            self::MODE_OVERALL     => 'ภาพรวม',
            self::MODE_AVERAGE     => 'ค่าเฉลี่ย',
        ];
        $total = $report['total'];

        $rows = [
            ['ช่วงเวลา', $rangeLabel],
            ['สร้างเมื่อ', EvaluationReportService::thaiDateTime($report['generated_at']) . ' น.'],
            ['เคาน์เตอร์ในรายงาน', implode(', ', array_map(fn ($c) => $c['label'] . ($c['is_online'] ? ' (Online)' : ''), $report['counters'])) ?: '-'],
            ['รูปแบบที่ส่งออก', implode(', ', array_map(fn ($m) => $modeLabels[$m] ?? $m, $modes))],
            ['จำนวนการประเมิน', $total['count']],
            ['คะแนนเฉลี่ยรวม', $total['average']],
            ['จำนวนรอบ Check-in', count($report['sessions'])],
            ['หมายเหตุ', 'คะแนนเฉลี่ย = ค่าเฉลี่ยของคะแนนทุกการประเมิน (ถ่วงตามจำนวน) · สีพื้น: เขียว ≥ 4.00, เหลือง 3.00–3.99, แดง < 3.00 · การประเมินจากเคาน์เตอร์ Online ไม่นับให้บุคลากร'],
        ];

        $row = 3;
        foreach ($rows as [$label, $value]) {
            $this->text($sheet, 1, $row, $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            if (is_int($value) || is_float($value)) {
                $this->number($sheet, 2, $row, $value, is_float($value) ? '0.00' : '0');
                if ($label === 'คะแนนเฉลี่ยรวม') {
                    $this->scoreFill($sheet, "B{$row}", $value);
                }
            } elseif ($value === null) {
                $this->text($sheet, 2, $row, '-');
            } else {
                $this->text($sheet, 2, $row, $value);
            }
            $row++;
        }

        // การกระจายคะแนน
        $row++;
        $this->text($sheet, 1, $row, 'การกระจายคะแนน');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        foreach ($report['distribution'] as $star => $count) {
            $this->text($sheet, 1, $row, "{$star} ดาว");
            $this->number($sheet, 2, $row, $count, '0');
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(90);
        $sheet->getStyle('B3:B' . $row)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    /** ตารางสรุป: แถว = บุคลากร/วัน, คอลัมน์ = (คะแนน, จำนวน) ของแต่ละเคาน์เตอร์ + คะแนนรวม */
    private function sheetPivot(Worksheet $sheet, string $title, string $rowHeader, array $pivot, array $counters, string $rangeLabel): void
    {
        $lastCol = 1 + count($counters) * 2 + 2;
        $this->title($sheet, 1, $title, $lastCol);
        $this->text($sheet, 1, 2, 'ช่วงเวลา: ' . $rangeLabel);

        // หัวตาราง 2 แถว: ชื่อเคาน์เตอร์ (รวม 2 ช่อง) / คะแนน + จำนวน
        $this->text($sheet, 1, 4, $rowHeader);
        $sheet->mergeCells('A4:A5');
        $col = 2;
        foreach ($counters as $counter) {
            $this->text($sheet, $col, 4, $counter['label'] . ($counter['is_online'] ? ' (Online)' : '') . "\n" . $counter['location']);
            $sheet->mergeCells($this->coord($col, 4) . ':' . $this->coord($col + 1, 4));
            $this->text($sheet, $col, 5, 'คะแนน');
            $this->text($sheet, $col + 1, 5, 'จำนวน');
            $col += 2;
        }
        $this->text($sheet, $col, 4, 'คะแนนรวม');
        $sheet->mergeCells($this->coord($col, 4) . ':' . $this->coord($col + 1, 4));
        $this->text($sheet, $col, 5, 'คะแนน');
        $this->text($sheet, $col + 1, 5, 'จำนวน');
        $this->headerStyle($sheet, 'A4:' . $this->coord($lastCol, 5));
        $sheet->getRowDimension(4)->setRowHeight(32);

        $row = 6;
        if (empty($pivot['rows'])) {
            $this->text($sheet, 1, $row, 'ไม่มีการประเมินในช่วงเวลานี้');
            $sheet->mergeCells('A' . $row . ':' . $this->coord($lastCol, $row));
            $row++;
        }
        foreach ($pivot['rows'] as $line) {
            $this->pivotLine($sheet, $row, $line, $counters);
            $row++;
        }
        $this->pivotLine($sheet, $row, $pivot['total'], $counters);
        $this->fill($sheet, 'A' . $row . ':' . $this->coord($lastCol, $row), self::TOTAL_FILL);
        $sheet->getStyle('A' . $row . ':' . $this->coord($lastCol, $row))->getFont()->setBold(true);

        $this->border($sheet, 'A4:' . $this->coord($lastCol, $row));
        $sheet->getColumnDimension('A')->setWidth(28);
        for ($c = 2; $c <= $lastCol; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($c % 2 === 0 ? 11 : 8);
        }
        $sheet->freezePane('B6');
    }

    private function pivotLine(Worksheet $sheet, int $row, array $line, array $counters): void
    {
        $this->text($sheet, 1, $row, $line['label']);
        $col = 2;
        foreach ($counters as $counter) {
            $cell = $line['cells'][$counter['counter_id']] ?? ['count' => 0, 'average' => null];
            $this->scoreCell($sheet, $col, $row, $cell['average']);
            $this->number($sheet, $col + 1, $row, $cell['count'], '0');
            $col += 2;
        }
        $this->scoreCell($sheet, $col, $row, $line['total']['average']);
        $this->number($sheet, $col + 1, $row, $line['total']['count'], '0');
    }

    private function sheetCounterDetails(Worksheet $sheet, array $details, string $rangeLabel): void
    {
        $headers = ['เคาน์เตอร์', 'รายละเอียด / ตำแหน่ง', 'ประเภท', 'สถานะ', 'จุดย่อย', 'จำนวนประเมิน', 'คะแนนเฉลี่ย',
            '5 ดาว', '4 ดาว', '3 ดาว', '2 ดาว', '1 ดาว', 'ความคิดเห็น', 'คำชี้แจง', 'รอบ Check-in', 'จำนวนบุคลากร', 'เวลาให้บริการรวม'];
        $this->title($sheet, 1, 'รายละเอียดเคาน์เตอร์', count($headers));
        $this->text($sheet, 1, 2, 'ช่วงเวลา: ' . $rangeLabel);
        $this->headerRow($sheet, 4, $headers);

        $row = 5;
        foreach ($details as $d) {
            $values = [$d['label'], $d['location'], $d['type'], $d['status'], $d['sub_codes']];
            foreach ($values as $i => $v) {
                $this->text($sheet, $i + 1, $row, $v);
            }
            $this->number($sheet, 6, $row, $d['summary']['count'], '0');
            $this->scoreCell($sheet, 7, $row, $d['summary']['average']);
            $col = 8;
            foreach ($d['distribution'] as $count) {
                $this->number($sheet, $col++, $row, $count, '0');
            }
            $this->number($sheet, 13, $row, $d['comment_count'], '0');
            $this->number($sheet, 14, $row, $d['note_count'], '0');
            $this->number($sheet, 15, $row, $d['session_count'], '0');
            $this->number($sheet, 16, $row, $d['staff_count'], '0');
            $this->text($sheet, 17, $row, $d['service_label']);
            $row++;
        }
        $this->border($sheet, 'A4:' . $this->coord(count($headers), max(4, $row - 1)));
        $this->widths($sheet, [14, 32, 24, 12, 22, 12, 12, 7, 7, 7, 7, 7, 12, 10, 12, 12, 18]);
        $sheet->freezePane('B5');
    }

    private function sheetEvaluations(Worksheet $sheet, string $title, array $evaluations, string $rangeLabel, int $startRow = 1): int
    {
        $headers = ['วันที่', 'เวลา', 'เคาน์เตอร์', 'รายละเอียดเคาน์เตอร์', 'บุคลากร', 'คะแนน', 'ความคิดเห็น', 'คำชี้แจงจากผู้ให้บริการ', 'เวลาเขียนคำชี้แจง'];

        if ($startRow === 1) {
            $this->title($sheet, 1, $title, count($headers));
            $this->text($sheet, 1, 2, 'ช่วงเวลา: ' . $rangeLabel);
            $headerRow = 4;
        } else {
            $this->sectionTitle($sheet, $startRow, $title);
            $headerRow = $startRow + 1;
        }
        $this->headerRow($sheet, $headerRow, $headers);

        $row = $headerRow + 1;
        if (empty($evaluations)) {
            $this->text($sheet, 1, $row, 'ไม่มีการประเมินในช่วงเวลานี้');
            $row++;
        }
        foreach ($evaluations as $e) {
            $this->text($sheet, 1, $row, $e['date_label']);
            $this->text($sheet, 2, $row, $e['time']);
            $this->text($sheet, 3, $row, $e['code']);
            $this->text($sheet, 4, $row, $e['location']);
            $this->text($sheet, 5, $row, $e['staff_name']);
            $this->scoreCell($sheet, 6, $row, $e['rating'], '0');
            $this->text($sheet, 7, $row, $e['comment']);
            $this->text($sheet, 8, $row, $e['note']);
            $this->text($sheet, 9, $row, $e['note_at']);
            $row++;
        }

        $lastRow = $row - 1;
        $this->border($sheet, 'A' . $headerRow . ':I' . $lastRow);
        $sheet->getStyle('G' . $headerRow . ':H' . $lastRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $this->widths($sheet, [12, 8, 11, 28, 24, 8, 50, 45, 17]);
        if ($startRow === 1) {
            $sheet->freezePane('A5');
        }
        return $row;
    }

    private function sheetSessions(Worksheet $sheet, string $title, array $sessions, string $rangeLabel, int $startRow = 1): int
    {
        $headers = ['วันที่', 'บุคลากร', 'เคาน์เตอร์', 'รายละเอียดเคาน์เตอร์', 'เวลากะ', 'เริ่มนั่ง', 'สิ้นสุด', 'ระยะเวลา (นาที)',
            'สาเหตุที่ออก', 'รับช่วงต่อจาก', 'ต่อเวลาถึง', 'จำนวนประเมิน', 'คะแนนเฉลี่ย'];

        if ($startRow === 1) {
            $this->title($sheet, 1, $title, count($headers));
            $this->text($sheet, 1, 2, 'ช่วงเวลา: ' . $rangeLabel);
            $headerRow = 4;
        } else {
            $this->sectionTitle($sheet, $startRow, $title);
            $headerRow = $startRow + 1;
        }
        $this->headerRow($sheet, $headerRow, $headers);

        $row = $headerRow + 1;
        if (empty($sessions)) {
            $this->text($sheet, 1, $row, 'ไม่มีการ Check-in ในช่วงเวลานี้');
            $row++;
        }
        foreach ($sessions as $s) {
            $values = [$s['date_label'], $s['staff_name'], $s['code'], $s['location'], $s['shift'], $s['in'], $s['out']];
            foreach ($values as $i => $v) {
                $this->text($sheet, $i + 1, $row, $v);
            }
            $this->number($sheet, 8, $row, $s['minutes'], '0');
            $this->text($sheet, 9, $row, $s['reason']);
            $this->text($sheet, 10, $row, $s['takeover_from'] ?? '');
            $this->text($sheet, 11, $row, $s['extended_until']);
            $this->number($sheet, 12, $row, $s['eval_count'], '0');
            $this->scoreCell($sheet, 13, $row, $s['average']);
            $row++;
        }

        $this->border($sheet, 'A' . $headerRow . ':M' . ($row - 1));
        if ($startRow === 1) {
            $this->widths($sheet, [12, 24, 11, 28, 13, 9, 11, 14, 22, 20, 11, 12, 12]);
            $sheet->freezePane('A5');
        }
        return $row;
    }

    /** 1 ชีตต่อ 1 เคาน์เตอร์ */
    private function sheetSingleCounter(Worksheet $sheet, array $d, string $rangeLabel): void
    {
        $this->title($sheet, 1, $d['label'] . ' — ' . $d['location'], 9);
        $this->text($sheet, 1, 2, 'ช่วงเวลา: ' . $rangeLabel);

        $info = [
            ['ประเภท', $d['type']],
            ['สถานะ', $d['status']],
            ['จุดย่อย', $d['sub_codes']],
            ['จำนวนการประเมิน', $d['summary']['count']],
            ['คะแนนเฉลี่ย', $d['summary']['average']],
            ['ความคิดเห็น / คำชี้แจง', $d['comment_count'] . ' / ' . $d['note_count'] . ' รายการ'],
            ['รอบ Check-in', $d['is_online'] ? 'ไม่มี (เคาน์เตอร์ Online)' : $d['session_count'] . ' รอบ · ' . $d['staff_count'] . ' คน'],
            ['เวลาให้บริการรวม', $d['service_label']],
        ];

        $row = 4;
        foreach ($info as [$label, $value]) {
            $this->text($sheet, 1, $row, $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            if ($label === 'คะแนนเฉลี่ย') {
                $this->scoreCell($sheet, 2, $row, $value);
            } elseif (is_int($value)) {
                $this->number($sheet, 2, $row, $value, '0');
            } else {
                $this->text($sheet, 2, $row, (string) $value);
            }
            $row++;
        }

        // การกระจายคะแนน
        $row++;
        $this->sectionTitle($sheet, $row, 'การกระจายคะแนน');
        $row++;
        foreach ($d['distribution'] as $star => $count) {
            $this->text($sheet, 1, $row, "{$star} ดาว");
            $this->number($sheet, 2, $row, $count, '0');
            $row++;
        }

        $row++;
        if (!$d['is_online']) {
            $row = $this->sheetSessions($sheet, 'รอบการ Check-in (เวลาเข้า–ออกจริง)', $d['sessions'], $rangeLabel, $row) + 1;
        }
        $this->sheetEvaluations($sheet, 'รายการประเมิน', $d['evaluations'], $rangeLabel, $row);

        // ชีตนี้มีหลายตาราง ใช้ความกว้างที่พอดีกับทุกตาราง
        $this->widths($sheet, [22, 26, 12, 28, 24, 10, 50, 45, 17, 20, 11, 12, 12]);
    }

    // ------------------------------------------------------------------
    // ตัวช่วย
    // ------------------------------------------------------------------

    private function newSheet(Spreadsheet $spreadsheet, string $title): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->safeTitle($title));
        return $sheet;
    }

    /** ชื่อชีต: ห้ามมี [ ] : * ? / \ ยาวไม่เกิน 31 ตัวอักษร และห้ามซ้ำ */
    private function safeTitle(string $title): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $title)) ?: 'Sheet', 0, 28);
        $candidate = $base;
        $i = 2;
        while (in_array(mb_strtolower($candidate), $this->usedTitles, true)) {
            $candidate = $base . ' ' . $i++;
        }
        $this->usedTitles[] = mb_strtolower($candidate);
        return $candidate;
    }

    private function coord(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    /** เขียนเป็นข้อความล้วน — ไม่ถูกตีความเป็นสูตร */
    private function text(Worksheet $sheet, int $col, int $row, $value): void
    {
        $sheet->setCellValueExplicit($this->coord($col, $row), (string) ($value ?? ''), DataType::TYPE_STRING);
    }

    private function number(Worksheet $sheet, int $col, int $row, $value, string $format = '0.00'): void
    {
        $coord = $this->coord($col, $row);
        $sheet->setCellValueExplicit($coord, (float) $value, DataType::TYPE_NUMERIC);
        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode($format);
        $sheet->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** ช่องคะแนน: ตัวเลข + สีพื้นตามระดับ / ไม่มีข้อมูลแสดง "-" */
    private function scoreCell(Worksheet $sheet, int $col, int $row, $value, string $format = '0.00'): void
    {
        if ($value === null) {
            $this->text($sheet, $col, $row, '-');
            $sheet->getStyle($this->coord($col, $row))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            return;
        }
        $this->number($sheet, $col, $row, $value, $format);
        $this->scoreFill($sheet, $this->coord($col, $row), $value);
    }

    private function scoreFill(Worksheet $sheet, string $range, $value): void
    {
        $this->fill($sheet, $range, self::SCORE_FILL[Evaluation::scoreClass($value)]);
    }

    private function fill(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    private function border(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E0');
    }

    private function title(Worksheet $sheet, int $row, string $text, int $spanCols): void
    {
        $this->text($sheet, 1, $row, $text);
        if ($spanCols > 1) {
            $sheet->mergeCells('A' . $row . ':' . $this->coord($spanCols, $row));
        }
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
    }

    private function sectionTitle(Worksheet $sheet, int $row, string $text): void
    {
        $this->text($sheet, 1, $row, $text);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
    }

    private function headerRow(Worksheet $sheet, int $row, array $headers): void
    {
        foreach ($headers as $i => $header) {
            $this->text($sheet, $i + 1, $row, $header);
        }
        $this->headerStyle($sheet, 'A' . $row . ':' . $this->coord(count($headers), $row));
    }

    private function headerStyle(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $this->fill($sheet, $range, self::HEADER_FILL);
    }

    private function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($width);
        }
    }
}