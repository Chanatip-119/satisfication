<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางปฏิบัติงาน</title>
    <!-- นำเข้า CSS -->
    <link rel="stylesheet" href="{{ asset('css/schedule.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo">
        </div>
        <div class="menu-category">หลัก</div>
        <a href="{{ url('/dashboard') }}" class="menu-item">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item active">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item">ส่งออกรายงาน</a>

        <a href="{{ url('/logout') }}" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- แจ้งเตือน -->
        @if(session('success'))
            <div style="background: #c6f6d5; color: #2f855a; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div style="background: #fed7d7; color: #c53030; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach($errors->all() as $err) <li>{{ $err }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="header-row">
            <div class="page-title">
                <h1>ตารางปฏิบัติงาน</h1>
                <p>ดูแลและแก้ไขว่าบุคลากรคนไหนอยู่เคาน์เตอร์ไหน ช่วงเวลาใด</p>
            </div>
            <div class="action-buttons">
                <button class="btn btn-outline"><i class="far fa-clock"></i> ตั้งเวลาเคาน์เตอร์</button>
                <button class="btn btn-outline" onclick="openModal('importModal')"><i class="fas fa-file-excel"></i> Import Excel</button>
                <button class="btn btn-yellow" onclick="openModal('addModal')">+ เพิ่มตาราง</button>
            </div>
        </div>

        <!-- Toolbar เปลี่ยนสัปดาห์ -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <button class="btn btn-outline" style="padding: 6px 12px;"><i class="fas fa-chevron-left"></i></button>
                <span style="font-weight: 600; font-size: 16px;">{{ $weekLabel }}</span>
                <button class="btn btn-outline" style="padding: 6px 12px;"><i class="fas fa-chevron-right"></i></button>
                <button class="btn btn-outline">สัปดาห์นี้</button>
            </div>
            <div style="display: flex; gap: 5px;">
            </div>
        </div>

        <!-- Legend (สีบ่งบอกสถานะ) -->
        <div style="margin-bottom: 15px; font-size: 13px; color: #718096; display: flex; gap: 15px;">
            <div style="display: flex; align-items: center;"><span style="display: inline-block; width: 12px; height: 12px; background: #e6fffa; border: 1px solid #319795; margin-right: 5px; border-radius: 2px;"></span> มีตาราง Check-in</div>
        </div>

        <!-- ตารางแสดงผลจริง 100% (ไม่มีข้อมูลหลอก) -->
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">เคาน์เตอร์</th>
                        <!-- วนลูปสร้างหัวตารางวันที่จริง -->
                        @foreach($weekDays as $day)
                            <th>
                                {{ $day->translatedFormat('D j M') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($counters as $counter)
                        <tr>
                            <td>
                                <div class="counter-title">เคาน์เตอร์ {{ $counter->counter_id }}</div>
                                <div class="counter-desc">{{ $counter->counter_location }}</div>
                            </td>
                            <!-- วนลูป 7 วัน เช็คข้อมูลตาราง -->
                            @foreach($weekDays as $day)
                                <td>
                                    @foreach($counter->countersubs as $sub)
                                        @foreach($sub->schedules as $sch)
                                            <!-- แมตช์วันที่ของ schedule ให้ตรงกับคอลัมน์ของวัน -->
                                            @if($sch->schedule_date == $day->format('Y-m-d'))
                                                <div class="schedule-card" onclick="openEditModal({{ $sch->schedule_id }}, '{{ $sch->start_time }}', '{{ $sch->end_time }}', '{{ $sch->schedule_date }}', {{ $sch->staff_id }})">
                                                    <div class="sch-time">{{ substr($sch->start_time,0,5) }} - {{ substr($sch->end_time,0,5) }}</div>
                                                    <div class="sch-name">{{ $sch->staff->staff_name ?? 'ไม่ระบุชื่อ' }}</div>
                                                </div>
                                            @endif
                                        @endforeach
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: เพิ่มตารางปฏิบัติงาน (Multi-Step) -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2>เพิ่มตารางปฏิบัติงาน</h2>
                    <p>กำหนดเวลาได้อิสระ — คน 1 คนอยู่หลายเคาน์เตอร์ได้ถ้าคนละช่วงเวลา</p>
                </div>
                <button class="close-btn" onclick="closeModal('addModal')">&times;</button>
            </div>

            <!-- Steps Indicator -->
            <div class="tabs">
                <div class="tab active" id="stepIndicator1">1. เลือกเคาน์เตอร์</div>
                <div class="tab" id="stepIndicator2">2. กำหนดเวลา</div>
                <div class="tab" id="stepIndicator3">3. มอบหมายคน</div>
            </div>

            <form action="{{ route('schedule.store') }}" method="POST">
                @csrf
                <div style="max-height: 50vh; overflow-y: auto; padding-right: 10px;">
                    <!-- Step 1 -->
                    <div class="tab-content active" id="stepContent1">
                        <label class="form-label">เคาน์เตอร์ (เลือกได้ 1 เคาน์เตอร์)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                            @foreach($counters as $counter)
                                <label style="cursor: pointer; padding: 15px; border: 1px solid #cbd5e0; border-radius: 8px; display: flex; align-items: center;">
                                    <!-- ดึง sub_id แรกของเคาน์เตอร์นั้นมาเป็นค่าตั้งต้น (ป้องกัน null) -->
                                    <input type="radio" name="counter_sub_id" value="{{ $counter->countersubs->first()->counter_sub_id ?? $counter->counter_id }}" required style="accent-color: #FFCC00; width: 18px; height: 18px;">
                                    <div style="margin-left: 12px;">
                                        <div style="font-weight: 700; color: #2d3748; font-size: 15px;">เคาน์เตอร์ {{ $counter->counter_id }}</div>
                                        <div style="font-size: 12px; color: #718096; margin-top: 2px;">{{ $counter->counter_location }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">ยกเลิก</button>
                            <button type="button" class="btn btn-yellow" onclick="nextStep(2)">ถัดไป</button>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="tab-content" id="stepContent2">
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group" style="flex: 1; margin: 0;">
                                <label class="form-label">เวลาเริ่ม</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="form-group" style="flex: 1; margin: 0;">
                                <label class="form-label">เวลาสิ้นสุด</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">วันที่ทำงาน</label>
                            <input type="date" name="schedule_date" class="form-control" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="prevStep(1)">กลับ</button>
                            <button type="button" class="btn btn-yellow" onclick="nextStep(3)">ถัดไป</button>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="tab-content" id="stepContent3">
                        <div class="form-group">
                            <label class="form-label">บุคลากรหลัก (Primary)</label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">— เลือกบุคลากร —</option>
                                @foreach($staffs as $staff)
                                    <option value="{{ $staff->staff_id }}">{{ $staff->staff_name }} (BUU-{{ str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" onclick="prevStep(2)">กลับ</button>
                            <button type="submit" class="btn btn-yellow">บันทึก</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: แก้ไข/ลบ ตาราง (เมื่อกดการ์ด) -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>จัดการตารางปฏิบัติงาน</h2>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            
            <form id="editForm" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">วันที่</label>
                    <input type="date" name="schedule_date" id="edit_date" class="form-control" required>
                </div>
                <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label">เวลาเริ่ม</label>
                        <input type="time" name="start_time" id="edit_start" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label class="form-label">เวลาสิ้นสุด</label>
                        <input type="time" name="end_time" id="edit_end" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">บุคลากร</label>
                    <select name="staff_id" id="edit_staff" class="form-control" required>
                        @foreach($staffs as $staff)
                            <option value="{{ $staff->staff_id }}">{{ $staff->staff_name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="modal-footer" style="justify-content: space-between;">
                    <button type="button" class="btn" style="color: #e53e3e; border-color: #feb2b2; background: #fff5f5;" onclick="deleteSchedule()">ลบตารางนี้</button>
                    <div>
                        <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">ยกเลิก</button>
                        <button type="submit" class="btn btn-yellow">บันทึก</button>
                    </div>
                </div>
            </form>

            <form id="deleteForm" method="GET" style="display: none;"></form>
        </div>
    </div>

    <!-- Modal: Import Excel -->
    <div id="importModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h2>Import ตารางปฏิบัติงานจาก Excel</h2>
                <button class="close-btn" onclick="closeModal('importModal')">&times;</button>
            </div>
            <div style="border: 2px dashed #cbd5e0; padding: 30px; text-align: center; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-file-excel" style="font-size: 40px; color: #38a169; margin-bottom: 10px;"></i>
                <p style="font-weight: 600; font-size: 16px;">อัปโหลดตารางปฏิบัติงาน .xlsx</p>
                <p style="font-size: 13px; color: #718096; margin-top: 5px;">คอลัมน์: วันที่ • เวลาเริ่ม • เวลาสิ้นสุด • รหัสบุคลากร • เคาน์เตอร์</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal('importModal')">ยกเลิก</button>
                <button class="btn btn-yellow">Import และสร้างตาราง</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // ระบบควบคุม Multi-step (เพิ่มตาราง)
        function nextStep(step) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById('stepContent' + step).classList.add('active');
            document.getElementById('stepIndicator' + step).classList.add('active');
        }

        function prevStep(step) { nextStep(step); }

        // ดึงข้อมูลมายัดใส่ Form เวลาจะแก้ไข
        function openEditModal(id, start, end, date, staffId) {
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_start').value = start.substring(0, 5);
            document.getElementById('edit_end').value = end.substring(0, 5);
            document.getElementById('edit_staff').value = staffId;
            
            // เปลี่ยน Action ยิงไปยัง ID นั้นๆ
            document.getElementById('editForm').action = "/schedule/update/" + id;
            document.getElementById('deleteForm').action = "/schedule/delete/" + id;
            
            openModal('editModal');
        }

        function deleteSchedule() {
            if(confirm('คุณแน่ใจหรือไม่ว่าต้องการลบตารางงานนี้?')) {
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>