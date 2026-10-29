<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>จัดการบุคลากร</title>
    {{-- [แก้ไข ระดับ 4] โหลด layout.css (โครงหน้าที่ใช้ร่วมกันทุกหน้า) ก่อน CSS ของหน้านี้ --}}
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
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
        <a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.index') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item {{ Route::is('staff.index') ? 'active' : '' }}">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item">QR Code</a>
        @unless($isEmployee)
        <div class="menu-category">ระบบ</div>
        
            <a href="{{ route('report.export.index') }}" class="menu-item {{ Route::is('report.export.index') ? 'active' : '' }}">ส่งออกรายงาน</a>
        @endunless

        <div class="user-profile-container">
            <div class="profile-dropdown-menu" id="profileDropdown">
                <a href="#" class="dropdown-item text-danger" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    ออกจากระบบ
                </a>
            </div>
            
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
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </aside>

    <main class="content">
        <div class="header-row">
            <div class="page-title">
                <h1>จัดการบุคลากร</h1>
                @if($isEmployee)<p>ดูรายชื่อบุคลากรและผลการปฏิบัติงาน</p>@else<p>เพิ่ม แก้ไข รีเซ็ต PIN Code (Unique ID 4 หลัก)</p>@endif
            </div>
            <div class="action-buttons">
                @unless($isEmployee)
                <button class="btn" onclick="openModal('modal-reset')"> Reset Unique ID ทั้งหมด</button>
                <button class="btn" onclick="openModal('modal-import')"> Import Excel</button>
                <a href="{{ route('staff.exportCsv') }}" class="btn"> Export CSV</a>
                <button class="btn btn-yellow" onclick="openModal('modal-add-staff')">+ เพิ่มบุคลากร</button>
                @endunless
            </div>
        </div>

        @if(session('success'))
        <div class="alert-banner" style="background-color: #f0fff4; border-color: #c6f6d5; color: #22543d;">
            <span>✅ {{ session('success') }}</span>
            <a href="javascript:void(0)" onclick="this.parentElement.style.display='none'" style="color: #22543d;">✕</a>
        </div>
        @endif

        @if(session('error'))
        <div class="alert-banner" style="background-color: #fff5f5; border-color: #fed7d7; color: #e53e3e;">
            <span>❌ {{ session('error') }}</span>
            <a href="javascript:void(0)" onclick="this.parentElement.style.display='none'" style="color: #e53e3e;">✕</a>
        </div>
        @endif

        {{-- [เพิ่มใหม่ ระดับ 3 ข้อ 12] แสดง validation error จาก Controller
             เดิม: หน้านี้แสดงแค่ session('error') ถ้ารีเซ็ตรหัสผ่านไม่ผ่าน validation ผู้ใช้จะไม่เห็นข้อความใด ๆ --}}
        @if($errors->any())
        <div class="alert-banner" style="background-color: #fff5f5; border-color: #fed7d7; color: #e53e3e;">
            <span>❌ {{ $errors->first() }}</span>
            <a href="javascript:void(0)" onclick="this.parentElement.style.display='none'" style="color: #e53e3e;">✕</a>
        </div>
        @endif

        @if(session('import_errors'))
        <div class="alert-banner" style="background-color: #fffbeb; border-color: #fef3c7; color: #b45309; flex-direction: column; align-items: flex-start;">
            <strong style="margin-bottom: 5px;">⚠️ รายละเอียด Import:</strong>
            <ul style="margin: 0; padding-left: 20px; font-size: 13px;">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @unless($isEmployee)
        @php $next = now()->startOfMonth()->addMonth(); @endphp
        <div class="alert-banner">
                <span>ตั้งค่ารีเซ็ต Unique ID อัตโนมัติ: ทุกวันที่ 1 ของเดือน — รีเซ็ตครั้งถัดไป {{ $next->locale('th')->translatedFormat('j M') }} {{ $next->year + 543 }}</span>
            <a href="javascript:void(0)" onclick="openModal('modal-reset')">แก้ไขการตั้งค่า</a>
        </div>
        @endunless

        <form action="{{ route('staff.index') }}" method="GET" class="filter-row" id="search-form">
            <input type="text" name="search" class="search-box" placeholder="ค้นหาชื่อหรือบทบาท...." value="{{ request('search') }}">
            <select name="status" class="dropdown-box" onchange="document.getElementById('search-form').submit();">
                <option value="">ทุกสถานะ</option>
                <option value="Active" {{ request('status') == 'Active' ? 'selected' : '' }}>Active</option>
                <option value="Inactive" {{ request('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </form>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อ-นามสกุล</th>
                        <th>บทบาท</th>
                        @unless($isEmployee)
                        <th>คะแนนเฉลี่ย</th>
                        @endunless
                        @unless($isEmployee)
                        <th>PIN CODE ( 4 หลัก )</th>
                        @endunless
                        <th>สถานะ</th>
                        @if($isEmployee)<th>รายละเอียด</th>@else<th>จัดการสิทธิ์</th>@endif
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
                        @unless($isEmployee)
                        <td>
                            @if($staff->avg_rating !== '-')
                                <span class="badge" style="background: #c6f6d5; color: #22543d; font-weight: 600;">{{ $staff->avg_rating }}</span>
                            @else
                                <span class="text-green">-</span>
                            @endif
                        </td>
                        @endunless
                        @unless($isEmployee)
                        <td>
                            {{ $staff->staff_pincode }}
                        </td>
                        @endunless
                        <td>
                            @php $isActive = ($staff->status ?? 'Inactive') === 'Active'; @endphp
                            <div class="status {{ $isActive ? 'active' : 'offline' }}">
                                <div class="status-dot"></div> {{ $isActive ? 'Active' : 'Inactive' }}
                            </div>
                        </td>
                        <td>
                            @if($isEmployee)
                                {{-- Employee จะเห็นปุ่มดูรายละเอียดเฉพาะแถวที่เป็นข้อมูลของตัวเองเท่านั้น --}}
                                @if((int) $staff->staff_id === (int) auth()->user()->staff_id)
                                    <button type="button" class="action-btn" onclick="openStaffPanel({{ $staff->staff_id }})">ดูรายละเอียด</button>
                                @else
                                    <span style="color: #a0aec0; font-size: 13px;">-</span>
                                @endif
                            @else
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <select class="dropdown-box role-select" data-staff-id="{{ $staff->staff_id }}" style="padding: 5px 10px; font-size: 13px; height: 32px;">
                                    @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}" {{ (int) $staff->role_id === (int) $role->role_id ? 'selected' : '' }}>{{ $role->role_name }}</option>
                                    @endforeach
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
                                        <button type="button" class="action-item" onclick="openResetPasswordModal({{ $staff->staff_id }}, '{{ addslashes($staff->staff_name) }}', '{{ 'BUU-' . str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) }}'); toggleDropdown(event, 'action-menu-{{ $staff->staff_id }}')">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                            รีเซ็ตรหัสผ่าน (Password)
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
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </main>

    <!-- Side Panel -->
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
                    @unless($isEmployee)
                    <div class="sp-info-item">
                        <span class="sp-info-label">PIN Code</span>
                        <span class="sp-info-val" id="sp-pin">-</span>
                    </div>
                    @endunless
                </div>

                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">สัดส่วนคะแนนที่ได้รับ</h4>
                <div id="sp-progress-container"></div>
                
                @unless($isEmployee)
                <h4 style="font-size: 13px; color: #718096; margin: 25px 0 15px;">การดำเนินการ</h4>
                <div style="display:flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="action-btn" onclick="openResetPasswordFromPanel()" style="width: 100%; justify-content: center; height: 38px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        รีเซ็ตรหัสผ่าน (Password)
                    </button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">ส่ง PIN ซ้ำ (Gmail)</button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">Reset PIN Code</button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">Export รายงาน</button>
                    <button class="action-btn text-danger" style="width: 100%; justify-content: center; height: 38px; border-color: #fc8181; background: #fff5f5;">ลบบัญชีถาวร</button>
                </div>
                @endunless
            </div>

            <div id="sp-tab-history" class="sp-section">
                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">ประวัติ Check-in 7 ครั้งล่าสุด</h4>
                <div id="sp-history-container"></div>
            </div>

            <div id="sp-tab-comments" class="sp-section">
                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">ความคิดเห็นล่าสุด</h4>
                <div id="sp-comments-container"></div>
            </div>
        </div>
    </div>

    @unless($isEmployee)
    <!-- Modal เพิ่มบุคลากร -->
    <div id="modal-add-staff" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header-flex">
                <div>
                    <h2>เพิ่มบุคลากรใหม่</h2>
                    <p>กรอกข้อมูลด้วยตนเอง</p>
                </div>
                <span class="close-btn" style="cursor: pointer; color: #a0aec0;" onclick="closeModal('modal-add-staff')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
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
                        <label>รหัสผ่าน (Password สำหรับ Login)</label>
                        <input type="password" name="password" minlength="6" placeholder="อย่างน้อย 6 ตัวอักษร (เว้นว่างเพื่อใช้ 123456)">
                    </div>
                    <div class="form-group">
                        <label>PIN CODE ( 4 หลัก )</label>
                        <input type="text" name="staff_pincode" placeholder="● ● ● ●" maxlength="4" required>
                    </div>
                </div>

                <div class="form-group full-width" style="margin-bottom: 20px;">
                    <label>ตำแหน่ง</label>
                    <input type="text" name="position" placeholder="เช่น บรรณารักษ์">
                </div>

                <div class="form-group full-width">
                    <label style="margin-bottom: 10px; display: block;">สิทธิ์การใช้งาน</label>
                    <div class="role-selector">
                        @foreach($roles as $role)
                        <label class="role-radio">
                            <input type="radio" name="role_id" value="{{ $role->role_id }}" {{ (int) $role->role_id === \App\Models\Staff::ROLE_EMPLOYEE ? 'checked' : '' }}>
                            <div class="radio-card">
                                <span class="radio-circle"></span>
                                {{ $role->role_name }}
                            </div>
                        </label>
                        @endforeach
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
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <div>
                    <h2>Reset Pin Code ทั้งชุด</h2>
                    <p style="color: #718096; font-size: 14px;">สุ่ม PIN ใหม่ให้บุคลากรทั้งหมด และส่งทาง Gmail</p>
                </div>
                <span class="close-btn" onclick="closeModal('modal-reset')" style="cursor: pointer; font-size: 24px;">&times;</span>
            </div>
            
            <form action="{{ route('staff.resetPins') }}" method="POST">
                @csrf
                <div class="modal-footer" style="margin-top: 30px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-reset')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">ยืนยัน Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Import -->
    <div id="modal-import" class="modal-overlay">
        <div class="modal-content" style="max-width: 620px;">
            <div class="modal-header">
                <h2>Import บุคลากรจาก Excel / CSV</h2>
                <span class="close-btn" onclick="closeModal('modal-import')">&times;</span>
            </div>
            <form action="{{ route('staff.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div id="drop-zone" style="border: 2px dashed #cbd5e0; padding: 30px; text-align: center; border-radius: 10px; margin-bottom: 20px; background: #f7fafc; cursor: pointer;" onclick="document.getElementById('excel-file-input').click();">
                    <h3>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</h3>
                    <input type="file" name="excel_file" id="excel-file-input" accept=".csv,.xlsx,.xls" style="display: none;" onchange="showFileName(this)">
                    <div id="file-name-display" style="display: none; margin-top: 15px; font-weight: 600;"></div>
                </div>
                <div style="background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-weight: 600; font-size: 14px; color: #2d3748;">รูปแบบไฟล์ที่รองรับ</span>
                        <a href="{{ route('staff.downloadTemplate') }}" class="btn" style="padding: 6px 14px; font-size: 13px;">ดาวน์โหลด Template</a>
                    </div>
                    <p style="font-size: 13px; color: #718096; margin: 0;">คอลัมน์: staff_id, staff_name, staff_email, staff_pincode, password, role_id, department, position</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal('modal-import')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">นำเข้าข้อมูลทั้งหมด</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset Password -->
    {{-- ===== [แก้ไข ระดับ 3 ข้อ 12] Modal รีเซ็ตรหัสผ่านให้ใช้งานได้จริง =====
         เดิม: 1) ฟอร์มไม่มีช่อง reset_type / new_password ที่ StaffController::resetPassword() ต้องใช้
               2) action="" จึงส่งไปที่ /staff (store) แล้วได้ validation error แทน
               3) z-index 1000 ต่ำกว่า Side Panel (1060) ถ้าเปิดจาก Panel Modal จะไปอยู่ข้างหลัง มองไม่เห็น
         ใหม่: เลือกได้ว่ารีเซ็ตเป็น 123456 หรือกำหนดเอง และตั้ง z-index ให้อยู่เหนือ Side Panel --}}
    <div id="modal-reset-password" class="modal-overlay" style="z-index: 1100;">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header">
                <div>
                    <h2>รีเซ็ตรหัสผ่านบุคลากร</h2>
                    <p style="color: #718096; font-size: 13.5px;" id="reset-pw-staff-info">บุคลากร: -</p>
                </div>
                <span class="close-btn" onclick="closeModal('modal-reset-password')">&times;</span>
            </div>

            <form id="form-reset-password" method="POST" action="" onsubmit="return validateResetPasswordForm()">
                @csrf
                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; border: 1px solid #cbd5e0; border-radius: 8px; cursor: pointer; font-size: 14px; color: #2d3748;">
                        <input type="radio" name="reset_type" value="default" checked onchange="toggleCustomPassword()">
                        <span>รีเซ็ตเป็นรหัสผ่านเริ่มต้น <strong>123456</strong></span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 15px; border: 1px solid #cbd5e0; border-radius: 8px; cursor: pointer; font-size: 14px; color: #2d3748;">
                        <input type="radio" name="reset_type" value="custom" onchange="toggleCustomPassword()">
                        <span>กำหนดรหัสผ่านใหม่เอง</span>
                    </label>
                </div>

                <div id="custom-password-box" style="display: none; margin-bottom: 10px;">
                    <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #4a5568;">รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                    <input type="password" name="new_password" id="reset-new-password" minlength="6" autocomplete="new-password" style="width: 100%; padding: 12px 15px; border: 1px solid #cbd5e0; border-radius: 8px; outline: none; font-family: 'Sarabun', sans-serif;">
                </div>
                {{-- ===== [จบ ข้อ 12: Modal] ===== --}}

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modal-reset-password')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">ยืนยันรีเซ็ตรหัสผ่าน</button>
                </div>
            </form>
        </div>
    </div>
    @endunless

    <!-- JavaScript ทั้งหมด -->
    <script>
        // ===== [เพิ่มใหม่ ระดับ 1 ข้อ 1] ป้องกัน XSS =====
        // แปลงอักขระพิเศษก่อนนำข้อความจาก API ไปใส่ใน innerHTML
        // ความคิดเห็นมาจากผู้รับบริการที่ไม่ต้องล็อกอิน ถ้าไม่แปลง โค้ดที่ฝังมาจะทำงานในเบราว์เซอร์ของแอดมิน
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, ch => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));
        }
        // ===== [จบ ข้อ 1: escapeHtml] =====

        function openModal(modalId) {
            const m = document.getElementById(modalId);
            if(m) m.style.display = 'flex';
        }

        function closeModal(modalId) {
            const m = document.getElementById(modalId);
            if(m) m.style.display = 'none';
        }

        function toggleDropdown(event, menuId) {
            event.stopPropagation();
            document.querySelectorAll('.action-menu').forEach(function(menu) {
                if (menu.id !== menuId) menu.classList.remove('show');
            });
            const targetMenu = document.getElementById(menuId);
            if(targetMenu) targetMenu.classList.toggle('show');
        }

        window.addEventListener('click', function(event) {
            if (!event.target.matches('.action-btn') && !event.target.closest('.action-btn')) {
                document.querySelectorAll('.action-menu.show').forEach(function(menu) {
                    menu.classList.remove('show');
                });
            }
        });

        function closeStaffPanel() {
            const panel = document.getElementById('staff-panel');
            const overlay = document.getElementById('staff-panel-overlay');
            if(panel) panel.classList.remove('open');
            if(overlay) {
                overlay.classList.remove('show');
                overlay.classList.remove('open');
            }
        }

        function switchPanelTab(tabName) {
            document.querySelectorAll('.sp-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sp-section').forEach(s => s.classList.remove('active'));
            
            if (event && event.target) {
                event.target.classList.add('active');
            }
            const targetTab = document.getElementById('sp-tab-' + tabName);
            if (targetTab) {
                targetTab.classList.add('active');
            }
        }

        let currentPanelStaffId = null;

        // ===== [เพิ่มใหม่ ระดับ 3 ข้อ 12] ฟังก์ชันรีเซ็ตรหัสผ่าน =====
        // เดิม: ปุ่มในเมนู "การดำเนินการ" และใน Side Panel เรียก openResetPasswordModal() / openResetPasswordFromPanel()
        //       ซึ่งไม่มีอยู่ในไฟล์ กดแล้วเกิด JS error และไม่มีอะไรเกิดขึ้น
        function openResetPasswordModal(staffId, staffName, staffCode) {
            const form = document.getElementById('form-reset-password');
            if (!form) return;

            form.action = '/staff/' + encodeURIComponent(staffId) + '/reset-password';
            form.reset();
            toggleCustomPassword();
            document.getElementById('reset-pw-staff-info').innerText = 'บุคลากร: ' + staffName + ' (' + staffCode + ')';
            openModal('modal-reset-password');
        }

        function openResetPasswordFromPanel() {
            if (!currentPanelStaffId) return;
            const staffName = document.getElementById('sp-name').innerText || '-';
            const staffCode = document.getElementById('sp-code').innerText || '-';
            openResetPasswordModal(currentPanelStaffId, staffName, staffCode);
        }

        function toggleCustomPassword() {
            const customRadio = document.querySelector('#form-reset-password input[name="reset_type"][value="custom"]');
            const box = document.getElementById('custom-password-box');
            const input = document.getElementById('reset-new-password');
            if (!customRadio || !box || !input) return;

            const isCustom = customRadio.checked;
            box.style.display = isCustom ? 'block' : 'none';
            input.required = isCustom;
            if (!isCustom) input.value = '';
        }

        function validateResetPasswordForm() {
            const isCustom = document.querySelector('#form-reset-password input[name="reset_type"][value="custom"]').checked;
            const newPassword = document.getElementById('reset-new-password').value;

            if (isCustom && newPassword.length < 6) {
                alert('รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
                return false;
            }

            const label = isCustom ? 'รหัสผ่านที่กำหนด' : '123456';
            return confirm('ยืนยันรีเซ็ตรหัสผ่านของ ' + document.getElementById('reset-pw-staff-info').innerText.replace('บุคลากร: ', '') + ' เป็น ' + label + ' ใช่หรือไม่?');
        }
        // ===== [จบ ข้อ 12: ฟังก์ชัน] =====

        // ===== ฟังก์ชันเปิด Side Panel พร้อมรองรับสิทธิ์ Employee =====
        function openStaffPanel(staffId) {
            @if($isEmployee)
                // ถ้าเป็น Employee ให้บังคับใช้ ID ของตัวเองทันที เพื่อป้องกันข้อผิดพลาด
                staffId = {{ auth()->user()->staff_id }};
            @endif

            currentPanelStaffId = staffId;

            const overlay = document.getElementById('staff-panel-overlay');
            if (overlay) overlay.classList.add('show');
            const panel = document.getElementById('staff-panel');
            if (panel) panel.classList.add('open');
            
            document.getElementById('sp-name').innerText = "กำลังโหลดข้อมูล...";
            document.getElementById('sp-code').innerText = "BUU-...";
            document.getElementById('sp-avg-rating').innerText = "-";
            document.getElementById('sp-total-checkins').innerText = "-";
            document.getElementById('sp-total-comments').innerText = "-";
            document.getElementById('sp-current-counter').innerText = "-";
            document.getElementById('sp-time-range').innerText = "-";
            document.getElementById('sp-progress-container').innerHTML = "";
            document.getElementById('sp-comments-container').innerHTML = '<p style="font-size:13px; color:#a0aec0;">กำลังโหลดความคิดเห็น...</p>';
            document.getElementById('sp-history-container').innerHTML = '<p style="font-size:13px; color:#a0aec0;">กำลังโหลดประวัติ...</p>';

            fetch('/staff/' + staffId, {
                headers: { 
                    'Accept': 'application/json', 
                    'X-Requested-With': 'XMLHttpRequest' 
                }
            })
            .then(async res => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'เกิดข้อผิดพลาดในการดึงข้อมูล');
                }
                return data;
            })
            .then(data => {
                document.getElementById('sp-name').innerText = data.name || '-';
                document.getElementById('sp-code').innerText = data.code || '-';
                
                const pinEl = document.getElementById('sp-pin');
                if (pinEl) {
                    pinEl.innerText = data.pin || '-';
                }

                document.getElementById('sp-avg-rating').innerText = data.avg_rating || '-';
                document.getElementById('sp-total-checkins').innerText = data.total_checkins ?? 0;
                document.getElementById('sp-total-comments').innerText = data.total_comments ?? 0;
                document.getElementById('sp-current-counter').innerText = data.current_counter || '-';
                document.getElementById('sp-time-range').innerText = data.time_range || '-';
                
                const statusEl = document.getElementById('sp-status');
                if (statusEl) {
                    // [แก้ไข ระดับ 3 ข้อ 16] แสดงสถานะการ Check-in เป็นข้อความที่ชัดเจน
                    // เดิม: แสดงค่า data.status ตรง ๆ ซึ่ง Controller ส่งมาเป็น 'Check-ins' / 'Offline'
                    //       จึงขึ้นคำว่า "Check-ins" สีเทาแม้คนนั้นกำลัง Check-in อยู่ และสับสนกับ Active ในตาราง (= ล็อกอินอยู่)
                    // (escape ตาม [ระดับ 1 ข้อ 1] ยังคงไว้)
                    const isCheckedIn = data.status === 'Active';
                    statusEl.innerHTML = '<div class="status-dot"></div> ' + escapeHtml(isCheckedIn ? 'Check-in อยู่' : 'ไม่ได้ Check-in');
                    statusEl.className = isCheckedIn ? 'status active' : 'status offline';
                }

                let progHtml = '';
                const ratingCounts = data.rating_counts || {};
                const totalEvals = data.total_evals || 0;

                for (let i = 5; i >= 1; i--) {
                    let cnt = ratingCounts[i] || 0;
                    let pct = totalEvals > 0 ? Math.round((cnt / totalEvals) * 100) : 0;
                    progHtml += `
                        <div class="sp-progress-row" style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:13px;">
                            <span style="color:#ecc94b;">★ ${i}</span>
                            <div class="sp-progress-bg" style="flex:1; background:#edf2f7; height:8px; border-radius:4px; overflow:hidden;">
                                <div class="sp-progress-fill fill-${i}" style="width: ${pct}%; background:#ecc94b; height:100%;"></div>
                            </div>
                            <span style="width:35px; text-align:right; color:#718096;">${pct}%</span>
                        </div>`;
                }
                document.getElementById('sp-progress-container').innerHTML = progHtml;

                // ===== [แก้ไข ระดับ 1 ข้อ 1] ความคิดเห็น: escape ทุกค่าที่มาจาก API =====
                // เดิม: ${c.counter || ...} / ${c.time || ''} / ${c.text || ...} ใส่ลง innerHTML ตรง ๆ
                //       และ let score = c.rating || 0; ไม่ได้จำกัดค่า 0–5
                let cmtHtml = '';
                if (!data.comments || data.comments.length === 0) {
                    cmtHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีความคิดเห็น</p>';
                } else {
                    data.comments.forEach(c => {
                        let score = Math.min(5, Math.max(0, parseInt(c.rating, 10) || 0));
                        let stars = '★'.repeat(score) + '☆'.repeat(5 - score);
                        cmtHtml += `
                            <div class="sp-card" style="padding:12px; background:#f8fafc; border-radius:8px; margin-bottom:10px; border:1px solid #e2e8f0;">
                                <div class="sp-card-header" style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:12px;">
                                    <div class="sp-card-title" style="color:#3182ce; font-weight:600;">${escapeHtml(c.counter || 'เคาน์เตอร์')}</div>
                                    <div class="sp-card-date" style="color:#a0aec0;">${escapeHtml(c.time || '')}</div>
                                </div>
                                <div class="sp-card-desc" style="color:#1a202c; font-weight:500; font-size:13px;">${escapeHtml(c.text || 'ไม่มีข้อความ')}</div>
                                <div class="star-rating" style="color:#d69e2e; font-size:12px; margin-top:4px;">${stars}</div>
                            </div>`;
                    });
                }
                document.getElementById('sp-comments-container').innerHTML = cmtHtml;
                // ===== [จบ ข้อ 1: ความคิดเห็น] =====

                // ===== [แก้ไข ระดับ 1 ข้อ 1] ประวัติ Check-in: escape ทุกค่าที่มาจาก API =====
                let histHtml = '';
                if (!data.history || data.history.length === 0) {
                    histHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีประวัติ Check-in</p>';
                } else {
                    data.history.forEach(h => {
                        histHtml += `
                            <div class="sp-card" style="padding:10px; border-bottom:1px solid #edf2f7; font-size:13px;">
                                <div class="sp-card-header" style="display:flex; justify-content:space-between; font-weight:600;">
                                    <div class="sp-card-title">${escapeHtml(h.date || '')} — ${escapeHtml(h.counter || '')}</div>
                                    <div class="sp-card-right" style="color:#2b6cb0;">${escapeHtml(h.avg || '-')} (${escapeHtml(h.count || 0)} ครั้ง)</div>
                                </div>
                                <div class="sp-card-desc" style="color:#718096; font-size:12px; margin-top:2px;">${escapeHtml(h.time || '')}</div>
                            </div>`;
                    });
                }
                document.getElementById('sp-history-container').innerHTML = histHtml;
                // ===== [จบ ข้อ 1: ประวัติ] =====
            })
            .catch(error => {
                console.error(error);
                document.getElementById('sp-name').innerText = error.message || "เกิดข้อผิดพลาดในการดึงข้อมูล";
            });
        }

        // จัดการเปลี่ยน Role ผ่าน Dropdown ในตาราง
        document.querySelectorAll('.role-select').forEach(function(select) {
            select.addEventListener('change', function() {
                const staffId = this.dataset.staffId;
                const roleId = this.value;
                const selectEl = this;
                const originalValue = selectEl.dataset.originalValue;

                showRoleToast('กำลังบันทึกสิทธิ์...', false);

                fetch('/staff/' + staffId, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ role_id: roleId })
                })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const validationMessage = Object.values(data.errors || {}).flat()[0];
                        if (res.status === 419) {
                            throw new Error('เซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่');
                        }
                        throw new Error(data.message || validationMessage || `บันทึกไม่สำเร็จ (รหัส ${res.status})`);
                    }
                    return data;
                })
                .then(data => {
                    const row = selectEl.closest('tr');
                    const badge = row.querySelector('.badge');
                    const roleLabel = selectEl.options[selectEl.selectedIndex]?.text || 'Staff';
                    if (badge) badge.textContent = roleLabel;

                    selectEl.dataset.originalValue = roleId;
                    selectEl.style.borderColor = '#48bb78';
                    setTimeout(() => { selectEl.style.borderColor = ''; }, 1500);
                    showRoleToast(data.message || 'เปลี่ยน role สำเร็จ', false);
                })
                .catch(err => {
                    if (originalValue) selectEl.value = originalValue;
                    showRoleToast(err.message || 'เกิดข้อผิดพลาดในการอัปเดตสิทธิ์', true);
                });
            });

            select.dataset.originalValue = select.value;
        });

        function showRoleToast(message, isError) {
            let toast = document.getElementById('staff-role-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'staff-role-toast';
                toast.style.cssText = 'position:fixed;right:24px;bottom:24px;z-index:3000;max-width:min(420px,calc(100vw - 32px));padding:14px 18px;border-radius:10px;color:#fff;font-size:14px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18);transition:opacity .2s;';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.background = isError ? '#c53030' : '#276749';
            toast.style.opacity = '1';
            toast.setAttribute('role', isError ? 'alert' : 'status');
            clearTimeout(toast.hideTimer);
            toast.hideTimer = setTimeout(() => { toast.style.opacity = '0'; }, isError ? 5000 : 2500);
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const container = document.querySelector('.user-profile-container');
            if(dropdown) dropdown.classList.toggle('show');
            if(container) container.classList.toggle('open');
        }
        
        window.addEventListener('click', function(e) {
            const container = document.querySelector('.user-profile-container');
            if (container && !container.contains(e.target)) {
                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) dropdown.classList.remove('show');
                container.classList.remove('open');
            }
        });
    </script>
</body>
</html>