<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด — ระบบประเมินความพึงพอใจ</title>
    <link rel="stylesheet" href="{{ asset('css/schedule.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            display: flex !important;
            flex-direction: column !important;
            height: 100vh !important;
        }
        .logout-btn {
            margin-top: auto !important;
            color: #fff !important;
            text-decoration: none !important;
            padding: 14px 16px 6px 16px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            border-top: 1px solid rgba(255, 255, 255, 0.35) !important;
        }
        .logout-btn:hover { opacity: 0.85; }
    </style>
</head>
<body>

    <!-- Sidebar พร้อมปุ่ม Logout ด้านล่างสุด -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo">
        </div>
        <div class="menu-category">หลัก</div>
        <a href="{{ url('/dashboard') }}" class="menu-item active">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item">ตารางปฏิบัติงาน</a>
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
        <!-- Header -->
        <div class="header-row">
            <div class="page-title">
                <h1>Dashboard Overview</h1>
                <p>Manage and resolve support tickets</p>
            </div>
            <div class="action-buttons">
                <form action="{{ url('/dashboard') }}" method="GET" id="periodForm" style="margin: 0;">
                    <div class="period-select-wrapper">
                        <i class="far fa-calendar-alt" style="color: #4a5568; font-size: 13px;"></i>
                        <select name="period" class="period-select" onchange="document.getElementById('periodForm').submit()">
                            <option value="today" {{ $period == 'today' ? 'selected' : '' }}>วันนี้</option>
                            <option value="week" {{ $period == 'week' ? 'selected' : '' }}>7 วันล่าสุด</option>
                            <option value="month" {{ $period == 'month' ? 'selected' : '' }}>เดือนนี้</option>
                            <option value="all" {{ $period == 'all' ? 'selected' : '' }}>ทั้งหมด</option>
                        </select>
                    </div>
                </form>
                <a href="{{ route('report.export.index') }}" class="btn btn-yellow" style="text-decoration: none;">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </div>

        <!-- 4 Summary KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label"><i class="far fa-star"></i> คะแนนเฉลี่ยรวม</div>
                <div class="kpi-value">{{ number_format($avgScore, 1) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><i class="far fa-check-square"></i> การประเมิน{{ $period == 'today' ? 'วันนี้' : '' }}</div>
                <div class="kpi-value">{{ number_format($totalEvalCount) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><i class="far fa-user-circle"></i> Staff Check-in อยู่</div>
                <div class="kpi-value">{{ number_format($staffCheckinCount) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label"><i class="far fa-comment"></i> ความคิดเห็นใหม่</div>
                <div class="kpi-value">{{ number_format($newCommentsCount) }}</div>
            </div>
        </div>

        <!-- Main 2-Column Grid -->
        <div class="dashboard-main-grid">
            <!-- Left Column -->
            <div class="left-col">
                <div class="panel-card">
                    <div class="panel-header">
                        <div class="panel-title">แนวโน้มคะแนนรายวัน 7 วันล่าสุด</div>
                        <div class="panel-subtitle">เฉลี่ยทุกเคาน์เตอร์</div>
                    </div>
                    <div style="height: 210px; position: relative;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-header">
                        <div class="panel-title">สถานะเคาน์เตอร์ทั้งหมด ณ ปัจจุบัน</div>
                    </div>
                    <table class="status-table">
                        <thead>
                            <tr>
                                <th style="width: 18%;">COUNTER</th>
                                <th style="width: 26%;">STAFF ปัจจุบัน</th>
                                <th style="width: 18%;">เวลา</th>
                                <th style="width: 14%; text-align: center;">คะแนนวันนี้</th>
                                <th style="width: 10%; text-align: center;">ประเมิน</th>
                                <th style="width: 14%; text-align: right;">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($counterStatusRows as $row): ?>
                                <tr>
                                    <td>
                                        <span class="counter-code-bold">{{ $row['counter_code'] }}</span>
                                        <span class="counter-floor-sub">{{ $row['location'] }}</span>
                                    </td>
                                    <td>
                                        <?php if($row['staff_name']): ?>
                                            <span style="font-weight: 700; color: #1a202c;">{{ $row['staff_name'] }}</span>
                                        <?php else: ?>
                                            <span class="staff-empty">— ไม่มี Staff</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #4a5568;">{{ $row['time_range'] }}</td>
                                    <td style="text-align: center;">
                                        <?php if($row['score'] !== null): ?>
                                            <?php
                                                $sClass = 'score-high';
                                                if ($row['score'] < 3.0) $sClass = 'score-low';
                                                elseif ($row['score'] < 4.0) $sClass = 'score-mid';
                                            ?>
                                            <span class="score-pill {{ $sClass }}">{{ number_format($row['score'], 1) }}</span>
                                        <?php else: ?>
                                            <span style="color: #a0aec0;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-weight: 700;">{{ $row['eval_count'] }}</td>
                                    <td style="text-align: right;">
                                        <?php $stLower = strtolower($row['status']); ?>
                                        <span class="status-pill {{ $stLower }}">
                                            <span class="status-dot"></span> {{ $row['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if(count($counterStatusRows) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #a0aec0; padding: 25px;">ยังไม่มีข้อมูลเคาน์เตอร์ในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-col">
                <div class="panel-card">
                    <div class="panel-header">
                        <div class="panel-title">การกระจายคะแนน</div>
                        <div class="panel-subtitle">{{ $period == 'today' ? 'วันนี้' : 'ตามช่วงที่เลือก' }}</div>
                    </div>

                    <?php
                        $emojis = [5 => '🤩', 4 => '😊', 3 => '😐', 2 => '😕', 1 => '😞'];
                        foreach ([5, 4, 3, 2, 1] as $star):
                            $cnt = $starCounts[$star] ?? 0;
                            $pct = $maxStarCount > 0 ? round(($cnt / $maxStarCount) * 100) : 0;
                    ?>
                        <div class="star-row">
                            <span class="star-emoji">{{ $emojis[$star] }}</span>
                            <span class="star-label">{{ $star }}ดาว</span>
                            <div class="star-bar-bg">
                                <div class="star-bar-fill star-bar-{{ $star }}" style="width: {{ $pct }}%;"></div>
                            </div>
                            <span class="star-count">{{ $cnt }}</span>
                        </div>
                    <?php endforeach; ?>

                    <div class="checkin-list-section">
                        <div class="panel-title" style="margin-bottom: 10px;">Staff ที่กำลัง Check-in</div>
                        <?php if(count($activeCheckinStaffs) > 0): ?>
                            <?php foreach($activeCheckinStaffs as $act): ?>
                                <div class="checkin-item">
                                    <div>
                                        <div class="checkin-staff-name">{{ $act['staff_name'] }}</div>
                                        <div class="checkin-meta">เคาน์เตอร์ {{ $act['counter_code'] }} — {{ $act['time_range'] }}</div>
                                    </div>
                                    <span class="status-pill active"><span class="status-dot"></span> Check-in</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size: 12px; color: #a0aec0; padding: 10px 0;">ยังไม่มีบุคลากรที่กำลัง Check-in ในขณะนี้</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel-card">
                    <div class="panel-header" style="margin-bottom: 8px;">
                        <div class="panel-title">คะแนนสูง & ต้องปรับปรุง</div>
                    </div>

                    <div class="perf-group">
                        <div class="perf-tag">สูงสุดวันนี้</div>
                        <?php if($highestStaff): ?>
                            <div class="perf-row">
                                <div>
                                    <div style="font-weight: 700; font-size: 14px; color: #1a202c;">{{ $highestStaff['name'] }}</div>
                                    <div style="font-size: 12px; color: #718096; margin-top: 2px;">เคาน์เตอร์ {{ $highestStaff['counter_code'] }}</div>
                                </div>
                                <span class="score-badge-lg score-high">{{ number_format($highestStaff['avg_score'], 1) }}</span>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 12px; color: #a0aec0;">ยังไม่มีข้อมูลคะแนนประเมิน</div>
                        <?php endif; ?>
                    </div>

                    <div class="perf-group" style="padding-top: 14px;">
                        <div class="perf-tag">ต้องปรับปรุง</div>
                        <?php if($lowestStaff): ?>
                            <div class="perf-row">
                                <div>
                                    <div style="font-weight: 700; font-size: 14px; color: #1a202c;">{{ $lowestStaff['name'] }}</div>
                                    <div style="font-size: 12px; color: #718096; margin-top: 2px;">เคาน์เตอร์ {{ $lowestStaff['counter_code'] }}</div>
                                </div>
                                <span class="score-badge-lg score-low">{{ number_format($lowestStaff['avg_score'], 1) }}</span>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 12px; color: #a0aec0;">ยังไม่มีข้อมูลเปรียบเทียบ</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Grid -->
        <div class="dashboard-bottom-grid">
            <div class="panel-card">
                <div class="panel-header">
                    <div class="panel-title">คะแนนเฉลี่ยรายเคาน์เตอร์</div>
                    <div class="panel-subtitle">{{ $period == 'today' ? 'วันนี้' : 'ตามช่วงที่เลือก' }}</div>
                </div>
                <?php foreach($counterAvgScores as $cItem): ?>
                    <?php $barW = min(max(($cItem['score'] / 5) * 100, 0), 100); ?>
                    <div class="counter-avg-row">
                        <div class="counter-avg-name">เคาน์เตอร์ {{ $cItem['counter_id'] }}</div>
                        <div class="counter-avg-bar-bg">
                            <div class="counter-avg-bar-fill" style="width: {{ $barW }}%;"></div>
                        </div>
                        <div class="counter-avg-val">{{ $cItem['score'] > 0 ? number_format($cItem['score'], 1) : '—' }}</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel-card">
                <div class="panel-header">
                    <div class="panel-title">ความคิดเห็นล่าสุด</div>
                    <div class="panel-subtitle">{{ $newCommentsCount }} รายการ{{ $period == 'today' ? 'วันนี้' : '' }}</div>
                </div>

                <?php if(count($recentComments) > 0): ?>
                    <?php foreach($recentComments as $cmt): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <span style="font-weight: 700; color: #2b6cb0;">
                                    {{ $cmt['counter_code'] }} {{ $cmt['staff_name'] ? '• ' . $cmt['staff_name'] : '' }}
                                </span>
                                <span style="color: #d69e2e; font-weight: 700;">
                                    <i class="fas fa-star"></i> {{ number_format($cmt['score'], 1) }}
                                    <span style="color: #a0aec0; font-weight: 400; margin-left: 6px;">{{ $cmt['time'] }}</span>
                                </span>
                            </div>
                            <div class="comment-text">{{ $cmt['comment'] }}</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: #a0aec0; font-size: 13px; padding: 30px 0;">
                        ยังไม่มีความคิดเห็นในระบบ
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const trendLabels = {!! json_encode($trendLabels) !!};
        const trendScores = {!! json_encode($trendScores) !!};

        const maxScore = Math.max(...trendScores);
        const pointColors = trendScores.map(s => (s === maxScore && s > 0) ? '#10b981' : '#2563eb');

        const ctx = document.getElementById('trendChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.18)');
        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'คะแนนเฉลี่ย',
                    data: trendScores,
                    borderColor: '#2563eb',
                    backgroundColor: gradient,
                    borderWidth: 2.5,
                    pointBackgroundColor: pointColors,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    fill: true,
                    tension: 0.25
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 5,
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            color: '#a0aec0',
                            font: { family: 'Prompt', size: 11 }
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: {
                        ticks: {
                            color: '#718096',
                            font: { family: 'Prompt', size: 11 }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>