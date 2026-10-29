<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code เคาน์เตอร์</title>
    {{-- [แก้ไข ระดับ 4] ใช้ layout.css แทน schedule.css
         เดิม: <link ... css/schedule.css?v={{ time() }}> — โหลดไฟล์ของหน้าตารางทั้งไฟล์เพื่อเอาแค่ layout --}}
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/qrcode.css') }}?v={{ filemtime(public_path('css/qrcode.css')) }}">
</head>
<body>
@php
    $isEmployee = auth()->check() && auth()->user()->isEmployee();
@endphp
    
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
        @unless($isEmployee)
        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item">ส่งออกรายงาน</a>
        @endunless
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
            <p>สร้างและดาวน์โหลด QR Code สำหรับแต่ละเคาน์เตอร์ พร้อมลิงก์ URL สำหรับประเมิน — กด Ctrl+P เพื่อพิมพ์ QR ทุกจุดในครั้งเดียว</p>
        </div>

        <div class="qr-grid">
            @foreach($counterSubs as $sub)
                @php
                    // [แก้ไข ระดับ 4] เปิดใช้งานเมื่อทั้งจุดย่อยและเคาน์เตอร์หลักเปิดอยู่
                    // เดิม: $isActive = $sub->is_active; (ไม่สนสถานะเคาน์เตอร์หลัก)
                    $isActive = $sub->is_active && $sub->counter && $sub->counter->is_active;
                    $locationText = $sub->counter ? $sub->counter->counter_location : '';
                    $displaySubtitle = $locationText;

                    // [แก้ไข ระดับ 4] ชื่อเคาน์เตอร์รูปแบบเดียวกันทั้งระบบ (เช่น "เคาน์เตอร์ 8.1")
                    // เดิม: "เคาน์เตอร์ {{ $sub->counter_sub_id }}" เช่น "เคาน์เตอร์ 7" ซึ่งไม่ตรงกับชื่อในหน้าจัดการเคาน์เตอร์
                    //       ตอนพิมพ์ไปติดจึงสับสนได้ง่ายว่าเป็นจุดไหน
                    $counterLabel = \App\Models\CounterSub::labelFor($sub->counter_sub_id);
                    $fileSafeLabel = str_replace([' ', '.'], '_', $counterLabel);
                    
                    $evaluationUrl = route('evaluation.create', ['counter_sub_id' => $sub->counter_sub_id]);
                    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($evaluationUrl) . "&margin=15";
                    $inputId = 'url-input-' . $sub->counter_sub_id;
                @endphp

                @if($isActive)
                    <div class="qr-card">
                        <div class="qr-image-container" id="qr-container-{{ $sub->counter_sub_id }}">
                            <img src="{{ $qrImageUrl }}" alt="QR Code {{ $counterLabel }}" crossorigin="anonymous" id="qr-img-{{ $sub->counter_sub_id }}">
                        </div>
                        <div class="qr-title">{{ $counterLabel }}</div>
                        <div class="qr-subtitle">{{ $displaySubtitle }}</div>
                        
                        <!-- ปุ่มดาวน์โหลด PNG -->
                        <button onclick="downloadQR('qr-img-{{ $sub->counter_sub_id }}', '{{ $fileSafeLabel }}')" class="btn-download">
                            <i class="fas fa-download"></i> ดาวน์โหลด PNG
                        </button>

                        <!-- ส่วน URL และปุ่มคัดลอก URL -->
                        {{-- [แก้ไข ระดับ 4] เพิ่ม class qr-url-group เพื่อซ่อนตอนพิมพ์ (ดู @media print ใน qrcode.css) --}}
                        <div class="qr-url-group" style="margin-top: 12px; width: 100%; text-align: left;">
                            <label style="font-size: 11px; color: #718096; display: block; margin-bottom: 4px; font-weight: 500;">ลิงก์ URL สำหรับประเมิน:</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="text" id="{{ $inputId }}" value="{{ $evaluationUrl }}" readonly style="flex: 1; font-size: 11px; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; color: #4a5568; outline: none;">
                                <button onclick="copyUrl('{{ $inputId }}', event)" title="คัดลอกลิงก์" style="padding: 6px 10px; background: #edf2f7; border: 1px solid #cbd5e0; border-radius: 6px; cursor: pointer; color: #4a5568; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#edf2f7'">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="qr-card disabled">
                        <div class="qr-image-container">
                            <div class="qr-placeholder">
                                <i class="fas fa-qrcode"></i>
                            </div>
                        </div>
                        <div class="qr-title">{{ $counterLabel }}</div>
                        <div class="qr-subtitle">ปิดให้บริการ</div>
                        <div class="btn-disabled">
                            <i class="fas fa-lock"></i> ปิดใช้งาน
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <!-- กล่องข้อความแจ้งเตือน (Toast Notification) อยู่ด้านบนตรงกลาง -->
    <div id="copy-toast">
        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
        <span>คัดลอกลิงก์ลงคลิปบอร์ดเรียบร้อยแล้ว</span>
    </div>

    <!-- JavaScript สำหรับดาวน์โหลด และ คัดลอกลิงก์ -->
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

        // [แก้ไข ระดับ 4] ใช้ navigator.clipboard (API ปัจจุบัน) และถอยไปใช้ execCommand บนเบราว์เซอร์เก่า
        // เดิม: ใช้ document.execCommand('copy') อย่างเดียว ซึ่งเป็น API ที่เลิกสนับสนุนแล้ว
        function copyUrl(inputId, event) {
            const inputElement = document.getElementById(inputId);
            if (!inputElement) return;
            const btn = event.currentTarget;

            const onCopied = () => {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check" style="color: #48bb78;"></i>';
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                }, 1500);
                showToast();
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(inputElement.value)
                    .then(onCopied)
                    .catch(() => alert('ไม่สามารถคัดลอกลิงก์ได้'));
                return;
            }

            inputElement.select();
            inputElement.setSelectionRange(0, 99999);
            try {
                if (document.execCommand('copy')) {
                    onCopied();
                } else {
                    alert('ไม่สามารถคัดลอกลิงก์ได้');
                }
            } catch (err) {
                alert('เบราว์เซอร์ไม่รองรับการคัดลอกอัตโนมัติ');
            }
        }

        let toastTimer;
        function showToast() {
            const toast = document.getElementById('copy-toast');
            toast.classList.add('show');
            
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => {
                toast.classList.remove('show');
            }, 2000);
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