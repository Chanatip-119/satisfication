<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งออกรายงาน</title>
    {{-- [แก้ไข ระดับ 4] โหลด layout.css แล้วตามด้วย export.css และเลิกโหลด schedule.css
         เดิม: export.css (ไม่มี ?v=) แล้วตามด้วย schedule.css ทำให้ .form-control ของหน้านี้ถูกทับ --}}
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/export.css') }}?v={{ filemtime(public_path('css/export.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Admin Logo">
        </div>

        <div class="menu-category">หลัก</div>
        <a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.index') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item active">ส่งออกรายงาน</a>

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

        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </div>
</div>

    <div class="content">
        
        <div class="page-title">
            <h1>ส่งออกรายงาน</h1>
            <p>เลือกประเภทไฟล์ ช่วงเวลา และเคาน์เตอร์ที่ต้องการ</p>
        </div>

        {{-- [เพิ่มใหม่ ระดับ 4] แสดง error จาก Controller
             เดิม: หน้านี้ไม่แสดง error เลย เช่น เลือก "กำหนดช่วงเอง" แล้วไม่ใส่วันที่ หน้าจะโหลดใหม่เฉย ๆ โดยไม่มีไฟล์และไม่บอกเหตุผล --}}
        @if($errors->any())
            <div role="alert" style="background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 600; max-width: 900px;">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="export-card">
            <form action="{{ route('report.export.download') }}" method="POST" id="export-form" onsubmit="return handleExportSubmit()">
                @csrf

                <!-- 1. ประเภทไฟล์ -->
                <div class="section-block">
                    <h3>1. ประเภทไฟล์</h3>
                    <div class="file-type-container">
                        <input type="radio" name="export_type" id="type_pdf" value="pdf" checked class="hidden-radio">
                        <label for="type_pdf" class="file-card">
                            <div class="radio-circle"></div>
                            <div class="file-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <path d="M9 15H7v-4h2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1zm0 0v4"></path>
                                </svg>
                            </div>
                            <div class="file-info">
                                <h4>PDF Report</h4>
                            </div>
                        </label>

                        <input type="radio" name="export_type" id="type_excel" value="excel" class="hidden-radio">
                        <label for="type_excel" class="file-card">
                            <div class="radio-circle"></div>
                            <div class="file-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#38a169" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="9" y1="12" x2="15" y2="18"></line>
                                    <line x1="15" y1="12" x2="9" y2="18"></line>
                                </svg>
                            </div>
                            <div class="file-info">
                                {{-- [แก้ไข ระดับ 4] เดิม: "Excel Report" — ไฟล์ที่ได้จริงเป็น .csv (เปิดใน Excel ได้) --}}
                                <h4>Excel Report (.csv)</h4>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. ช่วงเวลา -->
                <div class="section-block">
                    <h3>2. ช่วงเวลา</h3>
                    <select name="time_range" id="time_range" class="form-control" onchange="toggleCustomDate()">
                        <option value="today">วันนี้</option>
                        <option value="week">สัปดาห์นี้</option>
                        <option value="month">เดือนนี้</option>
                        <option value="custom">กำหนดช่วงเอง...</option>
                    </select>

                    <div id="custom_date_box" class="custom-date-box" style="display: none;">
                        <div class="date-group">
                            <label>จากวันที่</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" onchange="validateDates()">
                        </div>
                        <div class="date-group">
                            <label>ถึงวันที่</label>
                            <input type="date" name="end_date" id="end_date" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- 3. เคาน์เตอร์ -->
                <div class="section-block">
                    <div class="counter-header">
                        <h3>3. เคาน์เตอร์</h3>
                        <label class="checkbox-label">
                            {{-- [แก้ไข ระดับ 4] ติ๊ก "เลือกทั้งหมด" ไว้ตั้งแต่เปิดหน้า
                                 เดิม: ไม่ติ๊กอะไรเลย ซึ่ง Controller ตีความว่า "ทุกเคาน์เตอร์" ผู้ใช้จึงไม่รู้ว่าจะได้ข้อมูลทั้งหมด --}}
                            <input type="checkbox" name="select_all" id="select_all" onchange="toggleSelectAll()" checked> เลือกทั้งหมด
                        </label>
                    </div>
                    
                    <div class="counter-grid">
                        @foreach($counters as $counter)
                            <label class="counter-card">
                                <input type="checkbox" name="counters[]" value="{{ $counter->counter_id }}" class="counter-checkbox" checked>
                                <div class="counter-info">
                                    <h4>เคาน์เตอร์ {{ $counter->counter_id }}</h4>
                                    <p>{{ $counter->counter_location }}</p> 
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- [แก้ไข ระดับ 4] ลบ style="color: white;" ออก (สีตัวอักษรกำหนดใน export.css แล้ว) และเพิ่ม id --}}
                <button type="submit" class="btn-export" id="btn-export-submit">
                    <i class="fas fa-download"></i> Export ไฟล์
                </button>

            </form>
        </div>
    </div>

    <script>
        function toggleCustomDate() {
            const val = document.getElementById('time_range').value;
            const customBox = document.getElementById('custom_date_box');
            const isCustom = (val === 'custom');
            customBox.style.display = isCustom ? 'flex' : 'none';

            // [แก้ไข ระดับ 4] บังคับกรอกวันที่เมื่อเลือก "กำหนดช่วงเอง"
            document.getElementById('start_date').required = isCustom;
            document.getElementById('end_date').required = isCustom;
        }

        // ฟังก์ชันควบคุมความสมเหตุสมผลของวันที่ (ไม่ให้เลือก "ถึงวันที่" ก่อนหน้า "จากวันที่")
        function validateDates() {
            const startDate = document.getElementById('start_date').value;
            const endDateInput = document.getElementById('end_date');
            
            if (startDate) {
                endDateInput.min = startDate; // ล็อคให้เลือกวันสิ้นสุดได้ตั้งแต่วันที่เริ่มต้นเป็นต้นไป
                if (endDateInput.value && endDateInput.value < startDate) {
                    endDateInput.value = startDate; // เคลียร์หรือปรับวันสิ้นสุดให้อัตโนมัติถ้าเลือกค่าเก่ากว่า
                }
            }
        }

        function toggleSelectAll() {
            const selectAllChecked = document.getElementById('select_all').checked;
            const checkboxes = document.querySelectorAll('.counter-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAllChecked);
        }

        document.querySelectorAll('.counter-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                if (!this.checked) {
                    document.getElementById('select_all').checked = false;
                }
            });
        });

        // ===== [เพิ่มใหม่ ระดับ 4] ตรวจก่อนส่ง + กันกดซ้ำระหว่างรอสร้างไฟล์ =====
        // เดิม: ไม่ติ๊กเคาน์เตอร์เลยก็ส่งได้ (ได้ข้อมูลทุกเคาน์เตอร์โดยไม่ตั้งใจ)
        //       และการสร้าง PDF ใช้เวลาหลายวินาที ผู้ใช้มักกดซ้ำจนเซิร์ฟเวอร์สร้างไฟล์ซ้อนกัน
        function handleExportSubmit() {
            const selectAll = document.getElementById('select_all').checked;
            const anyCounter = document.querySelector('.counter-checkbox:checked');
            if (!selectAll && !anyCounter) {
                alert('กรุณาเลือกเคาน์เตอร์อย่างน้อย 1 เคาน์เตอร์ หรือกด "เลือกทั้งหมด"');
                return false;
            }

            const btn = document.getElementById('btn-export-submit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้างไฟล์...';

            // การดาวน์โหลดไฟล์ไม่ทำให้หน้าเปลี่ยน จึงเปิดปุ่มกลับเองหลังผ่านไปครู่หนึ่ง
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-download"></i> Export ไฟล์';
            }, 8000);
            return true;
        }
        // ===== [จบ ส่วนตรวจก่อนส่ง] =====
    </script>

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