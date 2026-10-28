@php
    // ===== [แก้ไข ระดับ 3 ข้อ 15] ระบุตำแหน่งไฟล์ฟอนต์ไทยให้ DomPDF =====
    // เดิม: มีแค่ font-family: 'THSarabunNew' แต่ไม่มี @font-face บอกว่าไฟล์ฟอนต์อยู่ที่ไหน
    //       DomPDF จึงใช้ฟอนต์ตั้งต้นที่ไม่มีอักษรไทย ข้อความไทยขึ้นเป็น ? หรือกล่องสี่เหลี่ยม
    // ต้องวางไฟล์ฟอนต์ไว้ที่ storage/fonts/THSarabunNew.ttf และ storage/fonts/THSarabunNew-Bold.ttf
    // แปลง \ เป็น / เพราะบน Windows (เช่น XAMPP) path มี \ ซึ่ง CSS ตีความผิด
    $fontRegular = str_replace('\\', '/', storage_path('fonts/THSarabunNew.ttf'));
    $fontBold = str_replace('\\', '/', storage_path('fonts/THSarabunNew-Bold.ttf'));
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        /* [แก้ไข ระดับ 3 ข้อ 15] ประกาศฟอนต์ไทยทั้งตัวปกติและตัวหนา */
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: normal;
            src: url("{{ $fontRegular }}") format('truetype');
        }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: bold;
            src: url("{{ $fontBold }}") format('truetype');
        }

        body { font-family: 'THSarabunNew', sans-serif; font-size: 16px; }

        /* [แก้ไข ระดับ 3 ข้อ 15] กำหนดความกว้างคอลัมน์ตายตัว และให้ตัดบรรทัดได้
           เดิม: ไม่มี table-layout: fixed ความคิดเห็นภาษาไทยยาว ๆ (ไม่มีเว้นวรรค) จึงดันตารางเกินขอบกระดาษ */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        th { background-color: #f4f4f4; font-weight: bold; }

        /* [แก้ไข ระดับ 3 ข้อ 15] แสดงหัวตารางซ้ำทุกหน้า เมื่อรายงานยาวหลายหน้า */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .col-date    { width: 15%; }
        .col-counter { width: 24%; }
        .col-staff   { width: 20%; }
        .col-rating  { width: 8%; text-align: center; }
        .col-comment { width: 33%; }

        .header { text-align: center; margin-bottom: 20px; }
        .empty-row { text-align: center; color: #888; padding: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>รายงานผลการประเมิน</h2>
        <p>ช่วงเวลา: {{ $dateLabel }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-date">วัน/เวลา</th>
                <th class="col-counter">เคาน์เตอร์</th>
                <th class="col-staff">รายชื่อเจ้าหน้าที่</th>
                <th class="col-rating">คะแนน</th>
                <th class="col-comment">ความคิดเห็น</th>
            </tr>
        </thead>
        <tbody>
            {{-- [แก้ไข ระดับ 3 ข้อ 15] ใช้ $rows ที่ Controller เตรียมไว้ (มีชื่อเคาน์เตอร์ที่อ่านเข้าใจได้แล้ว)
                 เดิม: วน $evaluations และแสดง counter_sub_id ดิบ ๆ ในคอลัมน์เคาน์เตอร์ --}}
            @forelse($rows as $row)
            <tr>
                <td class="col-date">{{ $row['date'] }} {{ $row['time'] }}</td>
                <td class="col-counter">{{ $row['counter'] }}</td>
                <td class="col-staff">{{ $row['staff'] }}</td>
                <td class="col-rating">{{ $row['rating'] }}</td>
                <td class="col-comment">{{ $row['comment'] !== '' ? $row['comment'] : '-' }}</td>
            </tr>
            @empty
            {{-- [เพิ่มใหม่ ระดับ 3 ข้อ 15] แสดงข้อความเมื่อไม่มีข้อมูล แทนตารางว่าง --}}
            <tr>
                <td colspan="5" class="empty-row">ไม่พบข้อมูลการประเมินในช่วงเวลาที่เลือก</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>