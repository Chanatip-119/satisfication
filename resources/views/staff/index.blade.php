<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบุคลากร - Admin</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Admin Logo">
        </div>

        <div class="menu-category">หลัก</div>
        <a href="#" class="menu-item">แดชบอร์ด</a>
        <a href="#" class="menu-item">ตารางปฏิบัติงาน</a>
        <a href="#" class="menu-item">รายงานเคาน์เตอร์</a>
        <a href="#" class="menu-item">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="#" class="menu-item">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item active">บุคลากร</a>
        <a href="#" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="#" class="menu-item">ส่งออกรายงาน</a>

        <a href="#" class="logout-btn">↩ Logout</a>
    </aside>

    <main class="content">
        
        <div class="header-row">
            <div class="page-title">
                <h1>จัดการบุคลากร</h1>
                <p>เพิ่ม แก้ไข รีเซ็ต PIN Code (Unique ID 6 หลัก)</p>
            </div>
            <div class="action-buttons">
                <button class="btn" onclick="openModal('modal-reset')"> Reset Unique ID ทั้งหมด</button>
                <button class="btn" onclick="openModal('modal-import')"> Import Excel</button>
                <form action="#" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn"> Export</button>
                </form>
                <button class="btn btn-yellow" onclick="openModal('modal-add-staff')">+ เพิ่มบุคลากร</button>
            </div>
        </div>

        <div class="alert-banner">
            <span> ตั้งค่ารีเซ็ต Unique ID อัตโนมัติ: ทุกวันที่ 1 ของเดือน— รีเซ็ตครั้งถัดไป 1 ก.ค. 2569</span>
            <a href="javascript:void(0)" onclick="openModal('modal-reset')">แก้ไขการตั้งค่า</a>
        </div>

        <div class="filter-row">
            <input type="text" class="search-box" placeholder=" ค้นหาชื่อหรือ....">
            <select class="dropdown-box">
                <option>ทุกสถานะ</option>
                <option>Active</option>
                <option>Offline</option>
            </select>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อ-นามสกุล</th>
                        <th>บทบาท</th>
                        <th>คะแนนเฉลี่ย</th>
                        <th>PIN CODE ( 4 หลัก )</th>
                        <th>สถานะ</th>
                        <th>จัดการสิทธิ์</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffs as $staff)
                    <tr>
                        <td>
                            <div class="staff-name">{{ $staff->staff_name }}</div>
                            <div class="staff-id">BUU-{{ str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) }}</div>
                        </td>
                        <td>
                            <span class="badge badge-staff">
                                {{ $staff->role ? $staff->role->role_name : 'Staff' }}
                            </span>
                        </td>
                        <td>
                            <span class="text-green">-</span>
                        </td>
                        <td>
                            {{ $staff->staff_pincode }}
                        </td>
                        <td>
                            <div class="status active">
                                <div class="status-dot"></div> Active
                            </div>
                        </td>
                        <td>
                            <select class="dropdown-box" style="padding: 5px; font-size: 13px; margin-right: 5px;">
                                <option>Employee</option>
                            </select>
                            <select class="dropdown-box" style="padding: 5px; font-size: 13px;">
                                <option>การดำเนินการ</option>
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </main>

    <div id="modal-add-staff" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2>เพิ่มบุคลากรใหม่</h2>
                    <p>กรอกข้อมูลด้วยตนเอง</p>
                </div>
                <span class="close-btn" onclick="closeModal('modal-add-staff')">&times;</span>
            </div>
            
            <form action="{{ route('staff.store') }}" method="POST">
                @csrf
                
                <div class="form-group full-width">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="staff_name" placeholder="นาย / นาง / นางสาว ..." required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>รหัสพนักงาน</label>
                        <input type="number" name="staff_id" placeholder="เช่น 010" required>
                    </div>
                    <div class="form-group">
                        <label>อีเมล (gmail)</label>
                        <input type="email" name="staff_email" placeholder="employee@go.buu.ac.th" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>ตำแหน่ง</label>
                        <input type="text" placeholder="เช่น บรรณารักษ์">
                    </div>
                    <div class="form-group">
                        <label>PIN CODE ( 4 หลัก )</label>
                        <input type="text" name="staff_pincode" placeholder="****" required>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>สิทธิ์การใช้งาน</label>
                    <div class="role-selector">
                        <label class="role-radio"><input type="radio" name="role_id" value="1"> Executive</label>
                        <label class="role-radio"><input type="radio" name="role_id" value="2"> SuperAdmin</label>
                        <label class="role-radio"><input type="radio" name="role_id" value="3" checked> Admin</label>
                        <label class="role-radio"><input type="radio" name="role_id" value="4"> Employee</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modal-add-staff')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-reset" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div>
                    <h2>Reset Pin Code ทั้งชุด</h2>
                    <p>สุ่ม PIN ใหม่ให้บุคลากรทั้งหมด 50 คน และส่งทาง Gmail</p>
                </div>
                <span class="close-btn" onclick="closeModal('modal-reset')">&times;</span>
            </div>
            
            <div style="background: #fff5f5; color: #e53e3e; padding: 15px; border-radius: 8px; border: 1px solid #fed7d7; font-size: 13px; margin-bottom: 20px;">
                 PIN เดิมของทุกคนจะใช้งานไม่ได้ทันทีหลัง Reset — บุคลากรต้องตรวจสอบ Gmail เพื่อรับ PIN ใหม่ก่อน Check-in ครั้งถัดไป
            </div>

            <form action="#" method="POST">
                @csrf
                <h4>รูปแบบการ Reset</h4>
                <label class="reset-option active">
                    <input type="radio" name="reset_type" value="now" checked>
                    <div>
                        <strong>Reset ทันที</strong><br>
                        <span style="font-size: 13px; color:#718096;">สุ่ม PIN ใหม่ทั้งหมด 50 รายการ และส่ง Gmail ทันทีที่กดยืนยัน</span>
                    </div>
                </label>
                <label class="reset-option">
                    <input type="radio" name="reset_type" value="schedule">
                    <div>
                        <strong>ตั้งเวลาล่วงหน้า (Recurring)</strong><br>
                        <span style="font-size: 13px; color:#718096;">ระบบ Reset ให้อัตโนมัติตามรอบที่กำหนด</span>
                    </div>
                </label>

                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modal-reset')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">ยืนยัน Reset</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-import" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Import บุคลากรจาก Excel</h2>
                <span class="close-btn" onclick="closeModal('modal-import')">&times;</span>
            </div>
            
            <form action="#" method="POST" enctype="multipart/form-data">
                @csrf
                <div style="border: 2px dashed #cbd5e0; padding: 40px; text-align: center; border-radius: 10px; margin-bottom: 20px; background: #f7fafc;">
                    <h3 style="color:#4a5568;">ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</h3>
                    <p style="color:#a0aec0; font-size: 13px;">.xlsx, .xls, .csv — ขนาดไม่เกิน 5MB</p>
                    <input type="file" name="excel_file" style="margin-top: 15px;">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modal-import')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow"> Import ทั้งหมด</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>

</body>
</html>