<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเคาน์เตอร์</title>
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
        <a href="{{ route('report.counter') }}" class="menu-item active">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item">บุคลากร</a>
        <a href="#" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="#" class="menu-item">ส่งออกรายงาน</a>

        <a href="#" class="logout-btn">↩ Logout</a>
    </aside>

    <main class="content">
        
        <div class="page-header-flex">
            <div class="page-title">
                <h1 style="font-size: 20px; color: #1a202c;">รายงานเคาน์เตอร์</h1>
                <p style="color: #718096; font-size: 13px;">วิเคราะห์คะแนนและความคิดเห็นรายเคาน์เตอร์</p>
            </div>
            <select class="dropdown-filter">
                @foreach($counters as $counter)
                    <option value="{{ $counter->counter_id }}">{{ $counter->counter_location }}</option>
                @endforeach
            </select>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">☆ คะแนนเฉลี่ย</div>
                <div class="stat-value">{{ $totalAvg }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">📋 การประเมินทั้งหมด</div>
                <div class="stat-value">{{ number_format($totalEvals) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">💬 ความคิดเห็น</div>
                <div class="stat-value">{{ number_format($totalComments) }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-title">👤 Staff ที่ผ่านมา</div>
                <div class="stat-value">{{ number_format($totalStaff) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-title">ตารางเคาน์เตอร์ย่อย</div>
            <table>
                <thead>
                    <tr>
                        <th>เคาน์เตอร์ย่อย</th>
                        <th>ช่วงเวลา</th>
                        <th>คะแนนเฉลี่ย</th>
                        <th>จำนวนการประเมิน</th>
                        <th>STAFF ปัจจุบัน</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($countersList as $c)
                    <tr>
                        <td>{{ $c->name }}</td>
                        <td>{{ $c->time }}</td>
                        <td>
                            <span class="rating-badge {{ $c->avg_rating >= 4 ? 'rating-good' : 'rating-bad' }}">
                                {{ $c->avg_rating }}
                            </span>
                        </td>
                        <td>{{ number_format($c->eval_count) }}</td>
                        <td>{{ $c->current_staff }}</td>
                        <td>
                            <div class="status-badge {{ strtolower($c->status) }}">
                                <div class="dot"></div> {{ $c->status }}
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </main>

</body>
</html>