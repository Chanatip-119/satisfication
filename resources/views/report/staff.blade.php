<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน Staff</title>
    <link rel="stylesheet" href="{{ asset('css/staff.css') }}?v={{ filemtime(public_path('css/staff.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/report.css') }}?v={{ filemtime(public_path('css/report.css')) }}">
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Admin Logo">
        </div>

        <div class="menu-category">หลัก</div>
        <a href="#" class="menu-item">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.index') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item {{ Route::is('staff.index') ? 'active' : '' }}">บุคลากร</a>
        <a href="#" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item {{ Route::is('report.export.index') ? 'active' : '' }}">ส่งออกรายงาน</a>

        <a href="#" class="logout-btn">↩ Logout</a>
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
                        <th>คะแนนเฉลี่ย</th>
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
                        <td>
                            @if($s->avg_rating !== '-')
                                <span class="rating-badge {{ $s->raw_rating >= 4 ? 'rating-good' : 'rating-bad' }}">
                                    {{ $s->avg_rating }}
                                </span>
                            @else
                                <span class="rating-none">-</span>
                            @endif
                        </td>
                        <td>{{ number_format($s->checkin_count) }}</td>
                        <td>{{ number_format($s->comment_count) }}</td>
                        <td>
                            <div class="status-badge {{ strtolower($s->status) }}">
                                <div class="dot"></div> {{ $s->status }}
                            </div>
                        </td>
                        <td>
                            <button class="btn-outline" onclick="openStaffPanel({{ $s->staff_id }})">ดูรายงาน</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </main>

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
                </div>
            </div>

            <div id="sp-tab-history" class="sp-section">
                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">ประวัติ 7 วันล่าสุด</h4>
                <div id="sp-history-container"></div>
            </div>

            <div id="sp-tab-comments" class="sp-section">
                <h4 style="font-size: 13px; color: #718096; margin-bottom: 15px;">ความคิดเห็นล่าสุด</h4>
                <div id="sp-comments-container"></div>
            </div>
        </div>
    </div>

    <script>
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
            document.getElementById('sp-code').innerText = "BUU-...";
            document.getElementById('sp-avg-rating').innerText = "-";
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
                document.getElementById('sp-pin').innerText = data.pin || '-';
                document.getElementById('sp-avg-rating').innerText = data.avg_rating;
                document.getElementById('sp-total-checkins').innerText = data.total_checkins;
                document.getElementById('sp-total-comments').innerText = data.total_comments;
                document.getElementById('sp-current-counter').innerText = data.current_counter;
                document.getElementById('sp-time-range').innerText = data.time_range;
                
                let statusHtml = '<div class="status-dot"></div> ' + data.status;
                document.getElementById('sp-status').innerHTML = statusHtml;
                document.getElementById('sp-status').className = data.status === 'Active' ? 'status active' : 'status offline';

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

                let cmtHtml = '';
                if(data.comments.length === 0) {
                    cmtHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีความคิดเห็น</p>';
                } else {
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
                }
                document.getElementById('sp-comments-container').innerHTML = cmtHtml;

                let histHtml = '';
                if(data.history.length === 0) {
                    histHtml = '<p style="font-size:13px; color:#a0aec0;">ไม่มีประวัติ Check-in</p>';
                } else {
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
                }
                document.getElementById('sp-history-container').innerHTML = histHtml;
            })
            .catch(error => {
                document.getElementById('sp-name').innerText = "เกิดข้อผิดพลาดในการดึงข้อมูล";
            });
        }
    </script>
</body>
</html>