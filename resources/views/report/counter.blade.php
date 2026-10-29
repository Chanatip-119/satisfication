<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานเคาน์เตอร์</title>
    {{-- [แก้ไข ระดับ 4] โหลด layout.css (โครงหน้าที่ใช้ร่วมกันทุกหน้า) ก่อน CSS ของหน้านี้ --}}
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
</head>
<body>
@php
    $isEmployee = auth()->check() && auth()->user()->isEmployee();
@endphp

    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Admin Logo">
        </div>

        <div class="menu-category">หลัก</div>
        {{-- [สิทธิ์ตามบทบาท] Employee ไม่เห็นเมนูแดชบอร์ด --}}
        @unless($isEmployee)<a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>@endunless
        {{-- [Check-in ฟีเจอร์ใหม่ ข้อ 1] เมนูการแจ้งเตือน (แสดงเฉพาะ Executive / Super_Admin / Admin) --}}
        @include('partials.notification-bell')
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.index') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item active">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item">QR Code</a>
        @unless($isEmployee)
        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item {{ Route::is('report.export.index') ? 'active' : '' }}">ส่งออกรายงาน</a>
        @endunless
        <div class="user-profile-container">
            <!-- เมนู Dropdown (ซ่อนไว้เริ่มต้น) -->
            <div class="profile-dropdown-menu" id="profileDropdown">
                <a href="#" class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    ออกจากระบบ
                </a>
            </div>
            
            <!-- ปุ่มกดดูโปรไฟล์ -->
        <div class="user-profile-toggle" onclick="toggleProfileDropdown()">
            <div class="avatar">
                <!-- ดึงตัวอักษรตัวแรกมาแสดง (เช็คเผื่อชื่อฟิลด์ต่างๆ และป้องกันค่าว่าง) -->
                @php
                    $displayName = 'User';
                    if(Auth::check()) {
                        $user = Auth::user();
                        $displayName = $user->name ?? $user->staff_name ?? $user->first_name ?? $user->username ?? 'User';
                    }
                @endphp
                {{ mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8') }}
            </div>
            
            <div class="user-info">
                <!-- แสดงชื่อเต็ม -->
                <div class="user-name">{{ $displayName }}</div>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="chevron-icon"><polyline points="18 15 12 9 6 15"></polyline></svg>
        </div>

            {{-- ===== [แก้ไข ระดับ 3 ข้อ 13] เพิ่มฟอร์ม logout ที่หายไป =====
                 เดิม: หน้านี้ไม่มี <form id="logout-form"> เมนู "ออกจากระบบ" จึงเกิด JS error และกดแล้วไม่มีอะไรเกิดขึ้น --}}
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
        {{-- [แก้ไข ระดับ 3 ข้อ 13] ปิด </div> ของ .user-profile-container ที่เดิมไม่มีแท็กปิด --}}
        {{-- ===== [จบ ข้อ 13] ===== --}}
    </aside>

    <main class="content">
        
        <div class="page-header-flex">
            <div class="page-title">
                <h1 style="font-size: 20px; color: #1a202c;">รายงานเคาน์เตอร์</h1>
                <p style="color: #718096; font-size: 13px;">วิเคราะห์คะแนนและความคิดเห็นรายเคาน์เตอร์</p>
            </div>

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
            <table class="counter-report-table">
                <thead>
                    <tr>
                        <th>ชื่อเคาน์เตอร์</th>
                        <th>เคาน์เตอร์ย่อย</th>
                        <th>คะแนนเฉลี่ย</th>
                        <th>จำนวนการประเมิน</th>
                        <th>STAFF ปัจจุบัน</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($countersList as $c)
                    <tr>
                        <td>{{ $c->counter_name }}</td>
                        <td>{{ $c->name }}</td>
                        <td>
                            {{-- [เพิ่มเติม ระดับ 3] จุดที่ยังไม่มีการประเมินแสดง "—" สีเทา
                                 เดิม: แสดง "0.0" ในป้ายเดียวกับคะแนนต่ำ ทำให้ดูเหมือนจุดนั้นได้คะแนนแย่ ทั้งที่ยังไม่มีใครประเมิน --}}
                            @if($c->eval_count > 0)
                                {{-- [สีคะแนน] เดิม: rating-good (≥ 4) / rating-bad (< 4) --}}
                                <span class="score-pill {{ \App\Models\Evaluation::scoreClass($c->avg_rating) }}">
                                    {{ $c->avg_rating }}
                                </span>
                            @else
                                <span class="score-none">—</span>
                            @endif
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
    <script>
    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        const container = document.querySelector('.user-profile-container');
        dropdown.classList.toggle('show');
        container.classList.toggle('open');
    }
    
    // คลิกพื้นที่อื่นบนหน้าจอเพื่อปิด Dropdown อัตโนมัติ
    window.addEventListener('click', function(e) {
        const container = document.querySelector('.user-profile-container');
        if (container && !container.contains(e.target)) {
            document.getElementById('profileDropdown').classList.remove('show');
            container.classList.remove('open');
        }
    });
</script>

</body>
</html>