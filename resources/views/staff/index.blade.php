<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบุคลากร</title>
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
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="#" class="menu-item">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item {{ Route::is('staff.index') ? 'active' : '' }}">บุคลากร</a>
        <a href="#" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="#" class="menu-item">ส่งออกรายงาน</a>

        <a href="#" class="logout-btn">↩ Logout</a>
    </aside>

    <main class="content">
        
        <div class="header-row">
            <div class="page-title">
                <h1>จัดการบุคลากร</h1>
                <p>เพิ่ม แก้ไข รีเซ็ต PIN Code (Unique ID 4 หลัก)</p>
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
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <select class="dropdown-box" style="padding: 5px 10px; font-size: 13px; height: 32px;">
                                    <option value="4" {{ $staff->role_id == 4 ? 'selected' : '' }}>Employee</option>
                                    <option value="3" {{ $staff->role_id == 3 ? 'selected' : '' }}>Admin</option>
                                    <option value="2" {{ $staff->role_id == 2 ? 'selected' : '' }}>SuperAdmin</option>
                                    <option value="1" {{ $staff->role_id == 1 ? 'selected' : '' }}>Executive</option>
                                </select>
                                
                                <div class="action-dropdown">
                                    <button class="action-btn" onclick="toggleDropdown(event, 'action-menu-{{ $staff->staff_id }}')">
                                        การดำเนินการ 
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </button>
                                    <div id="action-menu-{{ $staff->staff_id }}" class="action-menu">
                                        <button type="button" class="action-item" onclick="openStaffPanel({{ $staff->staff_id }}); toggleDropdown(event, 'action-menu-{{ $staff->staff_id }}')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                            ดูรายละเอียด
                                        </button>
                                        <button type="button" class="action-item">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            ส่ง PIN ซ้ำ (Gmail)
                                        </button>
                                        <button type="button" class="action-item">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
                                            Reset PIN Code
                                        </button>
                                        <a href="#" class="action-item">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            Export รายงาน
                                        </a>
                                        <div class="action-divider"></div>
                                        <form action="{{ route('staff.destroy', $staff->staff_id) }}" method="POST" style="margin: 0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-item text-danger" onclick="return confirm('ยืนยันการลบบัญชีนี้อย่างถาวร?');">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                ลบบัญชีถาวร
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </main>

    <!-- Side Panel สำหรับแสดงรายละเอียด Staff -->
    <div id="staff-panel-overlay" class="side-panel-overlay" onclick="closeStaffPanel()"></div>
    <div id="staff-panel" class="side-panel">
        <div class="sp-header">
            <div>
                <h2 id="sp-name">โหลดข้อมูล...</h2>
                <p id="sp-code">BUU-000</p>
            </div>
            <div class="sp-close" onclick="closeStaffPanel()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </div>
        </div>

        <div class="sp-tabs">
            <div class="sp-tab active" onclick="switchPanelTab('overview')">ภาพรวม</div>
            <div class="sp-tab" onclick="switchPanelTab('history')">ประวัติ Check-in</div>
            <div class="sp-tab" onclick="switchPanelTab('comments')">ความคิดเห็น</div>
        </div>

        <div class="sp-content">
            <!-- ภาพรวม -->
            <div id="sp-tab-overview" class="sp-section active">
                <div class="sp-stats-grid">
                    <div class="sp-stat-box">
                        <div class="sp-stat-title">คะแนนเฉลี่ย</div>
                        <div class="sp-stat-val text-green" id="sp-avg-rating">-</div>
                    </div>
                    <div class="sp-stat-box">
                        <div class="sp-stat-title">Check-in ทั้งหมด</div>
                        <div class="sp-stat-val" id="sp-total-checkins">0</div>
                    </div>
                    <div class="sp-stat-box">
                        <div class="sp-stat-title">ความคิดเห็น</div>
                        <div class="sp-stat-val" id="sp-total-comments">0</div>
                    </div>
                </div>

                <div class="sp-info-list">
                    <div class="sp-info-item">
                        <span class="sp-info-label">สถานะปัจจุบัน</span>
                        <span class="status active" style="padding: 2px 10px; font-size: 12px;" id="sp-status"><div class="status-dot"></div> Active</span>
                    </div>
                    <div class="sp-info-item">
                        <span class="sp-info-label">เคาน์เตอร์ปัจจุบัน</span>
                        <span class="sp-info-val" id="sp-current-counter">-</span>
                    </div>
                    <div class="sp-info-item">
                        <span class="sp-info-label">ช่วงเวลาวันนี้</span>
                        <span class="sp-info-val" id="sp-time-range">-</span>
                    </div>
                    <div class="sp-info-item">
                        <span class="sp-info-label">PIN Code</span>
                        <span class="sp-info-val" id="sp-pin">-</span>
                    </div>
                </div>

                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">สัดส่วนคะแนนที่ได้รับ</h4>
                <div id="sp-progress-container"></div>
                
                <h4 style="font-size: 13px; color: #718096; margin: 25px 0 15px;">การดำเนินการ</h4>
                <div style="display:flex; flex-direction: column; gap: 10px;">
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">ส่ง PIN ซ้ำ (Gmail)</button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">Reset PIN Code</button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">Export รายงาน</button>
                    <button class="action-btn text-danger" style="width: 100%; justify-content: center; height: 38px; border-color: #fc8181; background: #fff5f5;">ลบบัญชีถาวร</button>
                </div>
            </div>

            <!-- ประวัติ Check-in -->
            <div id="sp-tab-history" class="sp-section">
                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">ประวัติ 7 วันล่าสุด</h4>
                <div id="sp-history-container"></div>
            </div>

            <!-- ความคิดเห็น -->
            <div id="sp-tab-comments" class="sp-section">
                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">ความคิดเห็นล่าสุด</h4>
                <div id="sp-comments-container"></div>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มบุคลากร (อัปเดตตาม Figma) -->
    <div id="modal-add-staff" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header-flex">
                <div>
                    <h2>เพิ่มบุคลากรใหม่</h2>
                    <p>กรอกข้อมูลด้วยตนเอง</p>
                </div>
                <span class="close-btn" style="cursor: pointer; color: #a0aec0;" onclick="closeModal('modal-add-staff')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </span>
            </div>
            
            <form action="{{ route('staff.store') }}" method="POST">
                @csrf
                <div class="form-group full-width" style="margin-bottom: 20px;">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="staff_name" placeholder="นาย / นาง / นางสาว ..." required>
                </div>

                <div class="form-row" style="margin-bottom: 20px;">
                    <div class="form-group">
                        <label>รหัสพนักงาน</label>
                        <input type="text" name="staff_id" placeholder="เช่น BUU-010" required>
                    </div>
                    <div class="form-group">
                        <label>อีเมล (gmail)</label>
                        <input type="email" name="staff_email" placeholder="employee@go.buu.ac.th" required>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: 20px;">
                    <div class="form-group">
                        <label>ตำแหน่ง</label>
                        <input type="text" placeholder="เช่น บรรณารักษ์">
                    </div>
                    <div class="form-group">
                        <label>PIN CODE ( 4 หลัก )</label>
                        <div class="input-with-icon">
                            <input type="text" name="staff_pincode" placeholder="● ● ● ●" required>
                            <span class="input-icon-right" onclick="alert('สุ่ม PIN ใหม่')">
                                
                            </span>
                        </div>
                    </div>
                </div>

                <div class="form-group full-width">
                    <label style="margin-bottom: 10px; display: block;">สิทธิ์การใช้งาน</label>
                    <div class="role-selector">
                        <label class="role-radio">
                            <input type="radio" name="role_id" value="1">
                            <div class="radio-card">
                                <span class="radio-circle"></span>
                                Executive
                            </div>
                        </label>
                        <label class="role-radio">
                            <input type="radio" name="role_id" value="2">
                            <div class="radio-card">
                                <span class="radio-circle"></span>
                                SuperAdmin
                            </div>
                        </label>
                        <label class="role-radio">
                            <input type="radio" name="role_id" value="3" checked>
                            <div class="radio-card">
                                <span class="radio-circle"></span>
                                Admin
                            </div>
                        </label>
                        <label class="role-radio">
                            <input type="radio" name="role_id" value="4">
                            <div class="radio-card">
                                <span class="radio-circle"></span>
                                Employee
                            </div>
                        </label>
                    </div>
                </div>

                <div class="modal-footer" style="margin-top: 35px;">
                    <button type="button" class="btn btn-outline-modal" onclick="closeModal('modal-add-staff')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow" style="padding: 10px 25px;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset PIN -->
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

    <!-- Modal Import -->
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

        function toggleDropdown(event, menuId) {
            event.stopPropagation();
            document.querySelectorAll('.action-menu').forEach(function(menu) {
                if (menu.id !== menuId) {
                    menu.classList.remove('show');
                }
            });
            document.getElementById(menuId).classList.toggle('show');
        }

        window.addEventListener('click', function(event) {
            if (!event.target.matches('.action-btn') && !event.target.closest('.action-btn')) {
                document.querySelectorAll('.action-menu.show').forEach(function(menu) {
                    menu.classList.remove('show');
                });
            }
        });

        function closeStaffPanel() {
            document.getElementById('staff-panel').classList.remove('open');
            document.getElementById('staff-panel-overlay').classList.remove('show');
        }

        function switchPanelTab(tabName) {
            document.querySelectorAll('.sp-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sp-section').forEach(s => s.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('sp-tab-' + tabName).classList.add('active');
        }

        function openStaffPanel(staffId) {
            document.getElementById('staff-panel-overlay').classList.add('show');
            document.getElementById('staff-panel').classList.add('open');
            
            document.getElementById('sp-name').innerText = "กำลังโหลดข้อมูล...";
            
            fetch('/staff/' + staffId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('sp-name').innerText = data.name;
                document.getElementById('sp-code').innerText = data.code;
                document.getElementById('sp-avg-rating').innerText = data.avg_rating;
                document.getElementById('sp-total-checkins').innerText = data.total_checkins;
                document.getElementById('sp-total-comments').innerText = data.total_comments;
                document.getElementById('sp-current-counter').innerText = data.current_counter;
                document.getElementById('sp-time-range').innerText = data.time_range;
                document.getElementById('sp-pin').innerText = data.pin;
                
                let statusHtml = data.status === 'Active' 
                    ? '<div class="status-dot"></div> Active' 
                    : '<div class="status-dot" style="background:#a0aec0;"></div> Offline';
                document.getElementById('sp-status').innerHTML = statusHtml;
                document.getElementById('sp-status').className = data.status === 'Active' ? 'status active' : 'status offline';

                let progHtml = '';
                for(let i=5; i>=1; i--) {
                    let pct = Math.round((data.rating_counts[i] / data.total_evals) * 100) || 0;
                    progHtml += `
                        <div class="sp-progress-row">
                            <span style="color:#ecc94b;">★</span>
                            <div class="sp-progress-bg">
                                <div class="sp-progress-fill fill-${i}" style="width: ${pct}%"></div>
                            </div>
                            <span style="width:30px; text-align:right;">${pct}%</span>
                        </div>`;
                }
                document.getElementById('sp-progress-container').innerHTML = progHtml;

                let histHtml = '';
                if(data.history.length === 0) histHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีประวัติ Check-in</p>';
                data.history.forEach(h => {
                    histHtml += `
                        <div class="sp-card">
                            <div class="sp-card-header">
                                <div class="sp-card-title">${h.date} — ${h.counter}</div>
                                <div class="sp-card-right">${h.avg} (${h.count} ครั้ง)</div>
                            </div>
                            <div class="sp-card-desc">${h.time}</div>
                        </div>`;
                });
                document.getElementById('sp-history-container').innerHTML = histHtml;

                let cmtHtml = '';
                if(data.comments.length === 0) cmtHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีความคิดเห็น</p>';
                data.comments.forEach(c => {
                    let stars = '★'.repeat(c.rating) + '☆'.repeat(5 - c.rating);
                    cmtHtml += `
                        <div class="sp-card">
                            <div class="sp-card-header">
                                <div class="sp-card-title" style="color:#3182ce;">${c.counter}</div>
                                <div class="sp-card-date">${c.time}</div>
                            </div>
                            <div class="sp-card-desc" style="color:#1a202c; font-weight:500;">${c.text}</div>
                            <div class="star-rating">${stars}</div>
                        </div>`;
                });
                document.getElementById('sp-comments-container').innerHTML = cmtHtml;
            });
        }
    </script>
</body>
</html>