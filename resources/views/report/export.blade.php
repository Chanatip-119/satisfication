<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งออกรายงาน</title>
    <link rel="stylesheet" href="{{ asset('css/export.css') }}">
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

        <a href="{{ url('/') }}" class="logout-btn"></i>↩ Logout</a>
    </div>

    <div class="content">
        
        <div class="page-title">
            <h1>ส่งออกรายงาน</h1>
            <p>เลือกประเภทไฟล์ ช่วงเวลา และเคาน์เตอร์ที่ต้องการ</p>
        </div>

        <div class="export-card">
            <form action="{{ route('report.export.download') }}" method="POST">
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
                                <h4>Excel Report</h4>
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
                            <input type="checkbox" name="select_all" id="select_all" onchange="toggleSelectAll()"> เลือกทั้งหมด
                        </label>
                    </div>
                    
                    <div class="counter-grid">
                        @foreach($counters as $counter)
                            <label class="counter-card">
                                <input type="checkbox" name="counters[]" value="{{ $counter->counter_id }}" class="counter-checkbox">
                                <div class="counter-info">
                                    <h4>เคาน์เตอร์ {{ $counter->counter_id }}</h4>
                                    <p>{{ $counter->counter_location }}</p> 
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn-export" style="color: white;">
                    <i class="fas fa-download"></i> Export ไฟล์
                </button>

            </form>
        </div>
    </div>

    <script>
        function toggleCustomDate() {
            const val = document.getElementById('time_range').value;
            const customBox = document.getElementById('custom_date_box');
            customBox.style.display = (val === 'custom') ? 'flex' : 'none';
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
    </script>
</body>
</html>