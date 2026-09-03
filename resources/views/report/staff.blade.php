<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน Staff</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Admin Logo">
        </div>

        <div class="menu-category">หลัก</div>
        <a href="#" class="menu-item">แดชบอร์ด</a>
        <a href="#" class="menu-item">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item {{ Route::is('staff.index') ? 'active' : '' }}">บุคลากร</a>
        <a href="#" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="#" class="menu-item">ส่งออกรายงาน</a>

        <a href="#" class="logout-btn">↩ Logout</a>
    </aside>

    <main class="content">
        
        <div class="page-header-flex">
            <div class="page-title">
                <h1 style="font-size: 20px; color: #1a202c;">รายงาน Staff</h1>
                <p style="color: #718096; font-size: 13px;">ผลการประเมินรายบุคคล</p>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อ-นามสกุล</th>
                        <th>รหัสพนักงาน</th>
                        <th>คะแนนเฉลี่ย</th>
                        <th>ครั้งที่ CHECK-IN</th>
                        <th>ความคิดเห็น</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffReportList as $s)
                    <tr>
                        <td style="font-weight: 600;">{{ $s->name }}</td>
                        <td style="color: #718096;">{{ $s->code }}</td>
                        <td>
                            @if($s->avg_rating !== '-')
                                <span class="rating-badge {{ $s->raw_rating >= 4 ? 'rating-good' : 'rating-bad' }}">
                                    {{ $s->avg_rating }}
                                </span>
                            @else
                                <span class="rating-none">-</span>
                            @endif
                        </td>
                        <td>{{ number_format($s->checkin_count) }}</td>
                        <td>{{ number_format($s->comment_count) }}</td>
                        <td>
                            <div class="status-badge {{ strtolower($s->status) }}">
                                <div class="dot"></div> {{ $s->status }}
                            </div>
                        </td>
                        <td>
                            <button class="btn-outline">ดูรายงาน</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </main>

</body>
</html>