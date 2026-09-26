<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'THSarabunNew', sans-serif; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .header { text-align: center; margin-bottom: 20px; }
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
                <th>วัน/เวลา</th>
                <th>เคาน์เตอร์</th>
                <th>รายชื่อเจ้าหน้าที่</th>
                <th>คะแนน</th>
                <th>ความคิดเห็น</th>
            </tr>
        </thead>
        <tbody>
            @foreach($evaluations as $eval)
            <tr>
                <td>{{ \Carbon\Carbon::parse($eval->evaluation_at)->format('d/m/Y H:i') }}</td>
                <td>{{ $eval->checkin && $eval->checkin->schedule ? $eval->checkin->schedule->counter_sub_id : '-' }}</td>
                <td>{{ $eval->checkin && $eval->checkin->staff ? $eval->checkin->staff->staff_name : '-' }}</td>
                <td>{{ $eval->rating }}</td>
                <td>{{ $eval->comment }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>