<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>รายงาน Staff</title>
    {{-- [แก้ไข ระดับ 4] โหลด layout.css (โครงหน้าที่ใช้ร่วมกันทุกหน้า) ก่อน CSS ของหน้านี้ --}}
    <link rel="stylesheet" href="{{ asset('css/layout.css') }}?v={{ filemtime(public_path('css/layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
</head>
<body>
@php
    $isEmployee = auth()->check() && auth()->user()->isEmployee();
    // [สิทธิ์ตามบทบาท] เฉพาะ Executive (ตำแหน่งสูงสุด) เท่านั้นที่กดเปิดดู PIN ได้
    $isExecutive = auth()->check() && auth()->user()->isExecutive();
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

            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                @csrf
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="page-header-flex">
            <div class="page-title">
                <h1 style="font-size: 20px; color: #1a202c;">รายงาน Staff</h1>
                <p style="color: #718096; font-size: 13px;">ผลการประเมินรายบุคคล</p>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อ-นามสกุล</th>
                        <th>รหัสพนักงาน</th>
                        {{-- [สิทธิ์ตามบทบาท] Employee ไม่เห็นคอลัมน์คะแนนเฉลี่ย --}}
                        @unless($isEmployee)
                        <th>คะแนนเฉลี่ย</th>
                        @endunless
                        <th>ครั้งที่ CHECK-IN</th>
                        <th>ความคิดเห็น</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffReportList as $s)
                    <tr>
                        <td style="font-weight: 600;">{{ $s->name }}</td>
                        <td style="color: #718096;">{{ $s->code }}</td>
                        {{-- [สิทธิ์ตามบทบาท] Employee ไม่เห็นคะแนนเฉลี่ย --}}
                        @unless($isEmployee)
                        <td>
                            @if($s->avg_rating !== '-')
                                {{-- [สีคะแนน] เดิม: rating-good (≥ 4) / rating-bad (< 4) — คะแนน 2.5 กับ 3.9 ได้สีเดียวกัน --}}
                                <span class="score-pill {{ \App\Models\Evaluation::scoreClass($s->raw_rating) }}">
                                    {{ $s->avg_rating }}
                                </span>
                            @else
                                <span class="score-none">—</span>
                            @endif
                        </td>
                        @endunless
                        <td>{{ number_format($s->checkin_count) }}</td>
                        <td>{{ number_format($s->comment_count) }}</td>
                        <td>
                            @php
                                $statusText = $s->status ?? 'Offline';
                                // แปลงคำสถานะให้แสดงผลเป็น Active หรือ Offline
                                $isActive = (strtolower($statusText) === 'active' || strtolower($statusText) === 'checkin');
                                $displayStatus = $isActive ? 'Active' : 'Offline';
                            @endphp
                            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; {{ $isActive ? 'background: #f0fff4; color: #276749;' : 'background: #edf2f7; color: #4a5568;' }}">
                                <div style="width: 7px; height: 7px; border-radius: 50%; {{ $isActive ? 'background: #48bb78;' : 'background: #a0aec0;' }}"></div> 
                                {{ $displayStatus }}
                            </div>
                        </td>
                        <td>
                            @if($isEmployee)
                                @if((int) $s->staff_id === (int) auth()->user()->staff_id)
                                    <button type="button" class="btn-outline" onclick="openStaffPanel({{ $s->staff_id }})">ดูรายงาน</button>
                                @else
                                    <span style="color: #a0aec0; font-size: 13px;">-</span>
                                @endif
                            @else
                                <button type="button" class="btn-outline" onclick="openStaffPanel({{ $s->staff_id }})">ดูรายงาน</button>
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
                    {{-- [สิทธิ์ตามบทบาท] Employee ไม่เห็นคะแนนเฉลี่ยใน Side Panel --}}
                    @unless($isEmployee)
                    <div class="sp-stat-box">
                        <div class="sp-stat-title">คะแนนเฉลี่ย</div>
                        <div class="sp-stat-val text-green" id="sp-avg-rating">-</div>
                    </div>
                    @endunless
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
                    <div class="sp-info-item sp-info-item-long">
                        <span class="sp-info-label">ฝ่าย</span>
                        <span class="sp-info-val" id="sp-department">-</span>
                    </div>
                    <div class="sp-info-item sp-info-item-long">
                        <span class="sp-info-label">ตำแหน่ง</span>
                        <span class="sp-info-val" id="sp-position">-</span>
                    </div>
                    <div class="sp-info-item">
                        <span class="sp-info-label">สถานะปัจจุบัน</span>
                        <span class="status active" style="padding: 2px 10px; font-size: 12px;" id="sp-status"><div class="status-dot"></div> Active</span>
                        </span>
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
                    {{-- [สิทธิ์ตามบทบาท] PIN แสดงเป็น ●●●● เฉพาะ Executive มีปุ่มกดเปิดดู
                         เดิม: <span id="sp-pin">-</span> แสดง PIN จริงให้ทุกคนที่ไม่ใช่ Employee --}}
                    <div class="sp-info-item">
                        <span class="sp-info-label">PIN Code</span>
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="sp-info-val" id="sp-pin" style="letter-spacing: 3px; font-weight: 600;">●●●●</span>
                            @if($isExecutive)
                            <button type="button" id="sp-pin-toggle" onclick="togglePanelPin()" title="แสดง/ซ่อน PIN" style="background: none; border: 1px solid #e2e8f0; border-radius: 6px; padding: 3px 6px; cursor: pointer; color: #718096; display: inline-flex; align-items: center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            @endif
                        </span>
                    </div>
                    @endunless
                </div>

                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">สัดส่วนคะแนนที่ได้รับ</h4>
                <div id="sp-progress-container"></div>
                
                @unless($isEmployee)
                <h4 style="font-size: 13px; color: #718096; margin: 25px 0 15px;">การดำเนินการ</h4>
                <div style="display:flex; flex-direction: column; gap: 10px;">
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">ส่ง PIN ซ้ำ (Gmail)</button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">Reset PIN Code</button>
                    <button class="action-btn" style="width: 100%; justify-content: center; height: 38px;">Export รายงาน</button>
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

        // ===== [สิทธิ์ตามบทบาท] PIN ใน Side Panel แสดงเป็น ●●●● เสมอ =====
        // เซิร์ฟเวอร์ส่ง PIN มาให้เฉพาะ Executive (ดู StaffController::show) คนอื่นจะได้ null
        // Executive กดปุ่มรูปตาเพื่อสลับระหว่าง ●●●● กับ PIN จริง
        let currentPanelPin = null;

        function togglePanelPin() {
            const pinEl = document.getElementById('sp-pin');
            if (!pinEl || !currentPanelPin) return;
            const show = pinEl.dataset.revealed !== '1';
            pinEl.innerText = show ? currentPanelPin : '●●●●';
            pinEl.dataset.revealed = show ? '1' : '0';
        }
        // ===== [จบ สิทธิ์ตามบทบาท: PIN ใน Side Panel] =====

        // [สีคะแนน] สีตัวเลขคะแนนเฉลี่ยใน Side Panel ใช้เกณฑ์เดียวกับป้ายในตาราง
        // (≥ 4.0 เขียว | 3.0–3.9 เหลือง | < 3.0 แดง | ไม่มีคะแนน เทา)
        function scoreColor(value) {
            const score = parseFloat(value);
            if (isNaN(score)) return '#a0aec0';
            if (score >= 4) return '#047857';
            if (score >= 3) return '#b45309';
            return '#dc2626';
        }

        function closeStaffPanel() {
            document.getElementById('staff-panel').classList.remove('open');
            document.getElementById('staff-panel-overlay').classList.remove('show');
        }

        function switchPanelTab(tabName) {
            document.querySelectorAll('.sp-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sp-section').forEach(s => s.classList.remove('active'));
            
            if (event && event.target) {
                event.target.classList.add('active');
            }
            document.getElementById('sp-tab-' + tabName).classList.add('active');
        }

        function openStaffPanel(staffId) {
            @if($isEmployee)
                staffId = {{ auth()->user()->staff_id }};
            @endif

            document.getElementById('staff-panel-overlay').classList.add('show');
            document.getElementById('staff-panel').classList.add('open');
            
            document.getElementById('sp-name').innerText = "กำลังโหลดข้อมูล...";
            document.getElementById('sp-code').innerText = "BUU-...";
            document.getElementById('sp-department').innerText = "-";
            document.getElementById('sp-position').innerText = "-";
            // [สิทธิ์ตามบทบาท] กล่องคะแนนเฉลี่ยไม่มีในหน้าของ Employee จึงต้องเช็คก่อน
            const avgElReset = document.getElementById('sp-avg-rating');
            if (avgElReset) avgElReset.innerText = "-";
            document.getElementById('sp-total-checkins').innerText = "-";
            document.getElementById('sp-total-comments').innerText = "-";
            document.getElementById('sp-current-counter').innerText = "-";
            document.getElementById('sp-time-range').innerText = "-";

            fetch('/staff/' + staffId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('sp-name').innerText = data.name;
                document.getElementById('sp-code').innerText = data.code;
                document.getElementById('sp-department').innerText = data.department || '-';
                document.getElementById('sp-position').innerText = data.position || '-';
                // [สิทธิ์ตามบทบาท] ไม่แสดง PIN จริงทันที เก็บไว้ให้ Executive กดเปิดดูเอง
                currentPanelPin = data.pin || null;
                const pinEl = document.getElementById('sp-pin');
                if (pinEl) {
                    pinEl.innerText = '●●●●';
                    pinEl.dataset.revealed = '0';
                }
                const pinToggle = document.getElementById('sp-pin-toggle');
                if (pinToggle) pinToggle.style.display = currentPanelPin ? '' : 'none';
                // [สิทธิ์ตามบทบาท] กล่องคะแนนเฉลี่ยไม่มีในหน้าของ Employee
                const avgEl = document.getElementById('sp-avg-rating');
                if (avgEl) {
                    avgEl.innerText = data.avg_rating || '-';
                    avgEl.style.color = scoreColor(data.avg_rating); // [สีคะแนน] เดิม: เขียวเสมอ (class text-green)
                }
                document.getElementById('sp-total-checkins').innerText = data.total_checkins;
                document.getElementById('sp-total-comments').innerText = data.total_comments;
                document.getElementById('sp-current-counter').innerText = data.current_counter;
                document.getElementById('sp-time-range').innerText = data.time_range;
                
                // ===== [แก้ไข ระดับ 3 ข้อ 16] สถานะใน Side Panel แสดงได้ถูกต้อง =====
                // เดิม: หา element 'sp-status-dot' และ 'sp-status-text' ซึ่งไม่มีอยู่ในหน้า เงื่อนไขจึงไม่ผ่าน
                //       Panel จึงค้างคำว่า "Active" สีเขียวที่ hardcode ไว้ใน HTML สำหรับทุกคน
                // ใหม่: ใช้ element 'sp-status' ที่มีอยู่จริง และแสดงข้อความชัดเจนแบบเดียวกับหน้าจัดการบุคลากร
                const statusEl = document.getElementById('sp-status');
                if (statusEl) {
                    const isCheckedIn = data.status === 'Active';
                    statusEl.innerHTML = '<div class="status-dot"></div> ' + escapeHtml(isCheckedIn ? 'Check-in อยู่' : 'ไม่ได้ Check-in');
                    statusEl.className = isCheckedIn ? 'status active' : 'status offline';
                }
                // ===== [จบ ข้อ 16] =====

                let progHtml = '';
                for(let i=5; i>=1; i--) {
                    let pct = data.total_evals > 0 ? Math.round((data.rating_counts[i] / data.total_evals) * 100) : 0;
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

                // ===== [แก้ไข ระดับ 1 ข้อ 1] ความคิดเห็น: escape ทุกค่าที่มาจาก API =====
                // เดิม: ${c.counter} / ${c.time} / ${c.text} ใส่ลง innerHTML ตรง ๆ
                //       และ '★'.repeat(c.rating) ไม่ได้จำกัดค่า
                let cmtHtml = '';
                if (!data.comments || data.comments.length === 0) {
                    cmtHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีความคิดเห็น</p>';
                } else {
                    data.comments.forEach(c => {
                        let score = Math.min(5, Math.max(0, parseInt(c.rating, 10) || 0));
                        let stars = '★'.repeat(score) + '☆'.repeat(5 - score);
                        cmtHtml += `
                            <div class="sp-card">
                                <div class="sp-card-header">
                                    <div class="sp-card-title" style="color:#3182ce;">${escapeHtml(c.counter)}</div>
                                    <div class="sp-card-date">${escapeHtml(c.time)}</div>
                                </div>
                                <div class="sp-card-desc" style="color:#1a202c; font-weight:500;">${escapeHtml(c.text)}</div>
                                <div class="star-rating">${stars}</div>
                                <!-- [รายงาน+Online ข้อ 1] คำชี้แจงจากผู้ให้บริการ (escape แล้ว) -->
                                ${c.note ? `<div style="margin-top:8px; padding:8px 10px; background:#ebf8ff; border-left:3px solid #3182ce; border-radius:6px; font-size:12px; color:#2d3748;"><div style="font-weight:700; color:#2b6cb0; margin-bottom:2px;">คำชี้แจงจากผู้ให้บริการ · ${escapeHtml(c.note_time || '')} น.</div><div style="white-space:pre-wrap;">${escapeHtml(c.note)}</div></div>` : ''}
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
                            <div class="sp-card">
                                <div class="sp-card-header">
                                    <div class="sp-card-title">${escapeHtml(h.date)} — ${escapeHtml(h.counter)}</div>
                                    <div class="sp-card-right">${escapeHtml(h.avg)} (${escapeHtml(h.count)} ครั้ง)</div>
                                </div>
                                <div class="sp-card-desc">${escapeHtml(h.time)}</div>
                            </div>`;
                    });
                }
                document.getElementById('sp-history-container').innerHTML = histHtml;
                // ===== [จบ ข้อ 1: ประวัติ] =====
            })
            .catch(error => {
                document.getElementById('sp-name').innerText = "เกิดข้อผิดพลาดในการดึงข้อมูล";
            });
        }
    </script>

    <script>
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