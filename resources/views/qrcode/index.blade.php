<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code เคาน์เตอร์</title>
    <!-- โหลด CSS พร้อมกัน Cache -->
    <link rel="stylesheet" href="{{ asset('css/schedule.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ลิงก์ไปยังไฟล์ CSS ที่แยกออกมา -->
    <link rel="stylesheet" href="{{ asset('css/qrcode.css') }}?v={{ time() }}">
</head>
<body>
    
    <!-- แถบเมนูด้านซ้าย -->
    <div class="sidebar">
        <div class="sidebar-logo"><img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo"></div>
        <div class="menu-category">หลัก</div>
       <a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.*') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>
        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item active">QR Code</a>
        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item">ส่งออกรายงาน</a>

        <!-- ส่วนโปรไฟล์ผู้ใช้งาน -->
        <div class="user-profile-container">
            <!-- เมนู Dropdown -->
            <div class="profile-dropdown-menu" id="profileDropdown">
                <a href="#" class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    ออกจากระบบ
                </a>
            </div>
            
            <!-- ปุ่มกดดูโปรไฟล์ -->
            <div class="user-profile-toggle" onclick="toggleProfileDropdown()">
                <div class="avatar">
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
                    <div class="user-name">{{ $displayName }}</div>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="chevron-icon"><polyline points="18 15 12 9 6 15"></polyline></svg>
            </div>

            <!-- ฟอร์มสำหรับ Logout ซ่อนไว้ -->
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    </div> 

    <!-- ส่วนเนื้อหาหลัก -->
    <div class="content">
        <div class="qr-header">
            <h1>QR Code เคาน์เตอร์</h1>
            <p>สร้างและดาวน์โหลด QR Code สำหรับแต่ละเคาน์เตอร์</p>
        </div>

        <div class="qr-grid">
            @foreach($counterSubs as $sub)
                @php
                    $isActive = $sub->is_active;
                    $locationText = $sub->counter ? $sub->counter->counter_location : '';
                    $displaySubtitle = $locationText;
                    
                    $evaluationUrl = route('evaluation.create', ['counter_sub_id' => $sub->counter_sub_id]);
                    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($evaluationUrl) . "&margin=15";
                @endphp

                @if($isActive)
                    <div class="qr-card">
                        <div class="qr-image-container" id="qr-container-{{ $sub->counter_sub_id }}">
                            <img src="{{ $qrImageUrl }}" alt="QR Code {{ $sub->counter_sub_id }}" crossorigin="anonymous" id="qr-img-{{ str_replace('.', '_', $sub->counter_sub_id) }}">
                        </div>
                        <div class="qr-title">เคาน์เตอร์ {{ $sub->counter_sub_id }}</div>
                        <div class="qr-subtitle">{{ $displaySubtitle }}</div>
                        <button onclick="downloadQR('qr-img-{{ str_replace('.', '_', $sub->counter_sub_id) }}', 'เคาน์เตอร์_{{ $sub->counter_sub_id }}')" class="btn-download">
                            <i class="fas fa-download"></i> ดาวน์โหลด PNG
                        </button>
                    </div>
                @else
                    <div class="qr-card disabled">
                        <div class="qr-image-container">
                            <div class="qr-placeholder">
                                <i class="fas fa-qrcode"></i>
                            </div>
                        </div>
                        <div class="qr-title">เคาน์เตอร์ {{ $sub->counter_sub_id }}</div>
                        <div class="qr-subtitle">ปิดให้บริการ</div>
                        <div class="btn-disabled">
                            <i class="fas fa-lock"></i> ปิดใช้งาน
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <script>
        function downloadQR(imgId, filename) {
            const img = document.getElementById(imgId);
            if(!img) return;
            
            fetch(img.src)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename + '.png';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(() => {
                    const a = document.createElement('a');
                    a.href = img.src;
                    a.download = filename + '.png';
                    a.target = '_blank';
                    a.click();
                });
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const container = document.querySelector('.user-profile-container');
            dropdown.classList.toggle('show');
            container.classList.toggle('open');
        }
        
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