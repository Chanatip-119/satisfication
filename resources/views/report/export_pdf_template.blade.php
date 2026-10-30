@php
    // [ระดับ 3 ข้อ 15] ฟอนต์ไทยสำหรับ DomPDF — ต้องมีไฟล์ใน storage/fonts/
    $fontRegular = str_replace('\\', '/', storage_path('fonts/THSarabunNew.ttf'));
    $fontBold = str_replace('\\', '/', storage_path('fonts/THSarabunNew-Bold.ttf'));

    // [รายงาน+Online ข้อ 2] ตัวช่วยแสดงคะแนน: "4.25" + สีตามเกณฑ์เดียวกับทั้งระบบ / ไม่มีข้อมูล "-"
    $scoreClass = fn ($avg) => $avg === null ? 'score-none' : \App\Models\Evaluation::scoreClass($avg);
    $scoreText = fn ($avg) => $avg === null ? '-' : number_format($avg, 2);

    $counters = $report['counters'];
    $total = $report['total'];

    // ===== [แก้ PDF] แปลงสัญลักษณ์ที่ฟอนต์ไทยไม่มี ให้เป็นตัวอักษรธรรมดา =====
    // ฟอนต์ไทย (TH Sarabun New) ไม่มีสัญลักษณ์อย่าง ★ ≥ • และ DomPDF ไม่หยิบตัวอักษรจากฟอนต์อื่นมาแทนเหมือนเบราว์เซอร์
    // จึงแสดงเป็นสี่เหลี่ยม — ใช้กับข้อความที่ผู้ใช้พิมพ์เอง (ตำแหน่งเคาน์เตอร์ ความคิดเห็น คำชี้แจง)
    // ส่วนข้อความคงที่ในแม่แบบนี้ใช้แต่อักษรไทย/อังกฤษ/เครื่องหมายพื้นฐานอยู่แล้ว
    $safe = fn ($text) => strtr((string) $text, [
        '★' => '*', '☆' => '*', '≥' => '>=', '≤' => '<=', '×' => 'x', '÷' => '/',
        '–' => '-', '—' => '-', '·' => '-', '•' => '-', '…' => '...',
        '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '✓' => '/', '✔' => '/',
    ]);
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face { font-family: 'THSarabunNew'; font-style: normal; font-weight: normal; src: url("{{ $fontRegular }}") format('truetype'); }
        @font-face { font-family: 'THSarabunNew'; font-style: normal; font-weight: bold; src: url("{{ $fontBold }}") format('truetype'); }

        @page { margin: 18px 22px 26px; }
        body { font-family: 'THSarabunNew', sans-serif; font-size: 14px; color: #1a202c; }

        h1 { font-size: 22px; margin: 0 0 2px; }
        h2 { font-size: 17px; margin: 18px 0 6px; padding-bottom: 2px; border-bottom: 2px solid #FFCC00; }
        .meta { color: #4a5568; margin-bottom: 4px; }
        .note { color: #718096; font-size: 12px; margin: 2px 0 6px; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e0; padding: 3px 5px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        /* [แก้ PDF] เวลาและวันที่ไม่ตัดกลางคำ */
        .nowrap { white-space: nowrap; }
        th { background: #fff3bf; font-weight: bold; text-align: center; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .total-row td { background: #edf2f7; font-weight: bold; }
        .small { font-size: 12px; color: #718096; }

        .score-high { background: #e6fffa; color: #047857; font-weight: bold; }
        .score-mid  { background: #fef3c7; color: #b45309; font-weight: bold; }
        .score-low  { background: #fee2e2; color: #dc2626; font-weight: bold; }
        .score-none { color: #a0aec0; }

        .summary-box td { border: none; padding: 2px 12px 2px 0; }
        .staff-note { margin-top: 3px; padding: 2px 5px; background: #ebf8ff; border-left: 2px solid #3182ce; font-size: 12px; }
        .empty { text-align: center; color: #a0aec0; padding: 10px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

    {{-- ===== หัวรายงาน ===== --}}
    <h1>รายงานผลการประเมินความพึงพอใจ</h1>
    {{-- [แก้ PDF] ใช้ | แทน · และกรองสัญลักษณ์ในช่วงเวลา (มีขีด – คั่น) --}}
    <div class="meta">ช่วงเวลา: <strong>{{ $safe($rangeLabel) }}</strong> | สร้างเมื่อ {{ \App\Services\EvaluationReportService::thaiDateTime($report['generated_at']) }} น.</div>
    <table class="summary-box" style="width: auto;">
        <tr>
            <td>จำนวนการประเมิน: <strong>{{ number_format($total['count']) }}</strong></td>
            <td>คะแนนเฉลี่ยรวม: <strong class="{{ $scoreClass($total['average']) }}" style="padding: 0 4px;">{{ $scoreText($total['average']) }}</strong></td>
            <td>รอบ Check-in: <strong>{{ number_format(count($report['sessions'])) }}</strong></td>
            <td>
                การกระจาย:
                @foreach($report['distribution'] as $star => $count)
                    {{-- [แก้ PDF] เดิม: {{ $star }}★ — ฟอนต์ไทยไม่มี ★ จึงเป็นสี่เหลี่ยม --}}
                    {{ $star }} ดาว = {{ $count }}@if(!$loop->last) &nbsp;|&nbsp; @endif
                @endforeach
            </td>
        </tr>
    </table>
    {{-- [แก้ PDF] เดิมมี ≥ · – ซึ่งฟอนต์ไทยอาจไม่มี เปลี่ยนเป็นคำไทย --}}
    <div class="note">คะแนนเฉลี่ย = ค่าเฉลี่ยของคะแนนทุกการประเมิน (ถ่วงตามจำนวน) | สี: เขียว = 4.00 ขึ้นไป, เหลือง = 3.00 ถึง 3.99, แดง = ต่ำกว่า 3.00 | ตัวเลขในวงเล็บ = จำนวนการประเมิน | การประเมินจากเคาน์เตอร์ Online ไม่นับให้บุคลากร</div>

    {{-- ===== 1. รายละเอียดเคาน์เตอร์ ===== --}}
    <h2>1. รายละเอียดเคาน์เตอร์</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 9%;">เคาน์เตอร์</th>
                <th style="width: 20%;">รายละเอียด / ตำแหน่ง</th>
                <th style="width: 13%;">ประเภท</th>
                <th style="width: 7%;">สถานะ</th>
                <th style="width: 11%;">จุดย่อย</th>
                <th style="width: 7%;">ประเมิน</th>
                <th style="width: 7%;">คะแนนเฉลี่ย</th>
                <th style="width: 8%;">รอบ Check-in</th>
                <th style="width: 7%;">บุคลากร</th>
                <th style="width: 11%;">เวลาให้บริการรวม</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['counter_details'] as $d)
                <tr>
                    <td>{{ $d['label'] }}</td>
                    <td>{{ $safe($d['location']) }}</td>
                    <td>{{ $d['type'] }}</td>
                    <td class="center">{{ $d['status'] }}</td>
                    <td>{{ $d['sub_codes'] }}</td>
                    <td class="center">{{ number_format($d['summary']['count']) }}</td>
                    <td class="center {{ $scoreClass($d['summary']['average']) }}">{{ $scoreText($d['summary']['average']) }}</td>
                    <td class="center">{{ $d['is_online'] ? '-' : number_format($d['session_count']) }}</td>
                    <td class="center">{{ $d['is_online'] ? '-' : number_format($d['staff_count']) }}</td>
                    <td class="center">{{ $d['service_label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="empty">ไม่มีเคาน์เตอร์ในรายงาน</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ===== 2–3. ตารางสรุป (คอลัมน์ = เคาน์เตอร์ + คะแนนรวม) ===== --}}
    @php
        $pivots = [
            ['no' => 2, 'title' => 'สรุปคะแนนรายบุคลากร แยกตามเคาน์เตอร์', 'rowHeader' => 'บุคลากร', 'data' => $report['by_staff']],
            ['no' => 3, 'title' => 'สรุปคะแนนรายวัน แยกตามเคาน์เตอร์', 'rowHeader' => 'วันที่', 'data' => $report['by_date']],
        ];
        // ความกว้างคอลัมน์: ช่องแรก 18%, คะแนนรวม 9%, ที่เหลือแบ่งเท่ากันให้เคาน์เตอร์
        $counterWidth = count($counters) > 0 ? (73 / count($counters)) : 73;
    @endphp

    @foreach($pivots as $pivot)
        <h2>{{ $pivot['no'] }}. {{ $pivot['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 18%;">{{ $pivot['rowHeader'] }}</th>
                    @foreach($counters as $c)
                        <th style="width: {{ $counterWidth }}%;">{{ $c['label'] }}@if($c['is_online'])<br><span class="small">(Online)</span>@endif</th>
                    @endforeach
                    <th style="width: 9%;">คะแนนรวม</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pivot['data']['rows'] as $line)
                    <tr>
                        <td>{{ $line['label'] }}</td>
                        @foreach($counters as $c)
                            @php $cell = $line['cells'][$c['counter_id']] ?? ['count' => 0, 'average' => null]; @endphp
                            <td class="center {{ $scoreClass($cell['average']) }}">
                                {{ $scoreText($cell['average']) }}@if($cell['count'] > 0) <span class="small">({{ $cell['count'] }})</span>@endif
                            </td>
                        @endforeach
                        <td class="center {{ $scoreClass($line['total']['average']) }}">
                            {{ $scoreText($line['total']['average']) }}@if($line['total']['count'] > 0) <span class="small">({{ $line['total']['count'] }})</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($counters) + 2 }}" class="empty">ไม่มีการประเมินในช่วงเวลานี้</td></tr>
                @endforelse

                @if(!empty($pivot['data']['rows']))
                    @php $t = $pivot['data']['total']; @endphp
                    <tr class="total-row">
                        <td>{{ $t['label'] }}</td>
                        @foreach($counters as $c)
                            @php $cell = $t['cells'][$c['counter_id']] ?? ['count' => 0, 'average' => null]; @endphp
                            <td class="center">{{ $scoreText($cell['average']) }}@if($cell['count'] > 0) <span class="small">({{ $cell['count'] }})</span>@endif</td>
                        @endforeach
                        <td class="center">{{ $scoreText($t['total']['average']) }}@if($t['total']['count'] > 0) <span class="small">({{ $t['total']['count'] }})</span>@endif</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach

    {{-- ===== 4. รอบการ Check-in (เวลาเริ่ม–สิ้นสุดที่นั่งจริง) ===== --}}
    <h2 class="page-break">4. รอบการ Check-in (เวลาเริ่ม - สิ้นสุดที่นั่งจริง)</h2>
    <table>
        <thead>
            <tr>
                {{-- [แก้ PDF] ปรับความกว้าง: เดิมช่อง "เวลากะ" แคบจนเวลาถูกตัดเป็น 08:00–22:0 / 0
                     และช่อง "สิ้นสุด" ต้องกว้างขึ้นเพราะอาจมีวันที่ (ข้ามวัน) --}}
                <th style="width: 8%;">วันที่</th>
                <th style="width: 13%;">บุคลากร</th>
                <th style="width: 6%;">เคาน์เตอร์</th>
                <th style="width: 15%;">รายละเอียดเคาน์เตอร์</th>
                <th style="width: 10%;">เวลากะ</th>
                <th style="width: 6%;">เริ่ม</th>
                <th style="width: 9%;">สิ้นสุด</th>
                <th style="width: 9%;">ระยะเวลา</th>
                <th style="width: 12%;">สาเหตุที่ออก</th>
                <th style="width: 5%;">ประเมิน</th>
                <th style="width: 7%;">คะแนนเฉลี่ย</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['sessions'] as $s)
                <tr>
                    <td class="center">{{ $s['date_label'] }}</td>
                    <td>
                        {{ $s['staff_name'] }}
                        @if($s['takeover_from'])<br><span class="small">รับช่วงต่อจาก {{ $s['takeover_from'] }}</span>@endif
                    </td>
                    <td class="center">{{ $s['code'] }}</td>
                    <td>{{ $safe($s['location']) }}</td>
                    <td class="center nowrap">{{ $safe($s['shift']) }}</td>
                    <td class="center">{{ $s['in'] }}</td>
                    <td class="center">
                        {{ $s['out'] }}
                        @if($s['extended_until'])<br><span class="small">ต่อเวลาถึง {{ $s['extended_until'] }}</span>@endif
                    </td>
                    <td class="center">{{ $s['duration'] }}</td>
                    <td>{{ $s['reason'] }}</td>
                    <td class="center">{{ $s['eval_count'] }}</td>
                    <td class="center {{ $scoreClass($s['average']) }}">{{ $scoreText($s['average']) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="empty">ไม่มีการ Check-in ในช่วงเวลานี้</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ===== 5. รายการประเมิน + คำชี้แจงจากผู้ให้บริการ ===== --}}
    <h2 class="page-break">5. รายการประเมินทั้งหมด</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 10%;">วัน/เวลา</th>
                <th style="width: 7%;">เคาน์เตอร์</th>
                <th style="width: 15%;">รายละเอียดเคาน์เตอร์</th>
                <th style="width: 14%;">บุคลากร</th>
                <th style="width: 6%;">คะแนน</th>
                <th style="width: 48%;">ความคิดเห็น / คำชี้แจงจากผู้ให้บริการ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['evaluations'] as $e)
                <tr>
                    <td class="center">{{ $e['date_label'] }}<br>{{ $e['time'] }} น.</td>
                    <td class="center">{{ $e['code'] }}</td>
                    <td>{{ $safe($e['location']) }}</td>
                    <td>{{ $e['staff_name'] }}</td>
                    <td class="center {{ $scoreClass($e['rating']) }}">{{ $e['rating'] }}</td>
                    <td>
                        {{ $e['comment'] !== '' ? $safe($e['comment']) : '-' }}
                        @if($e['note'] !== '')
                            <div class="staff-note"><strong>คำชี้แจง ({{ $e['note_at'] }}):</strong> {{ $safe($e['note']) }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">ไม่มีการประเมินในช่วงเวลานี้</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>