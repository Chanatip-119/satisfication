<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางปฏิบัติงาน</title>
    <link rel="stylesheet" href="{{ asset('css/schedule.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <div class="sidebar">
        <div class="sidebar-logo"><img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo"></div>
        <div class="menu-category">หลัก</div>
        <a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item active">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item">รายงาน Staff</a>
        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item {{ Route::is('staff.*') ? 'active' : '' }}">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item {{ request()->is('/qrcode') ? 'active' : '' }}">QR Code</a>
        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item">ส่งออกรายงาน</a>
        <a href="{{ url('/') }}" class="logout-btn"></i>↩ Logout</a>
    </div>

    <div class="content">
        @if(session('success'))
            <div class="alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach($errors->all() as $err): ?><li>{{ $err }}</li><?php endforeach; ?>
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

        <div class="toolbar-row">
            <div class="week-nav-group">
                <a href="{{ route('schedule.index', ['week_offset' => $weekOffset - 1]) }}" class="btn btn-outline" style="padding: 6px 12px; text-decoration: none;"><i class="fas fa-chevron-left"></i></a>
                <span class="week-label">{{ $weekLabel }}</span>
                <a href="{{ route('schedule.index', ['week_offset' => $weekOffset + 1]) }}" class="btn btn-outline" style="padding: 6px 12px; text-decoration: none;"><i class="fas fa-chevron-right"></i></a>
                <a href="{{ route('schedule.index') }}" class="btn btn-outline" style="text-decoration: none;">สัปดาห์นี้</a>
            </div>

            <div class="view-toggle-group">
                <button type="button" class="btn-view-toggle" onclick="openCounterListModal()">รายเคาน์เตอร์</button>
            </div>
        </div>

        <!-- แถบสัญลักษณ์สีแสดงสถานะตามจริง (Figma Legend) -->
        <div class="status-legend-row">
            <div class="legend-item"><span class="legend-box checkin"></span> Check-in อยู่</div>
            <div class="legend-item"><span class="legend-box waiting"></span> มีตาราง รอ Check-in</div>
            <div class="legend-item"><span class="legend-box sick"></span> ลาป่วย/ไม่มา</div>
            <div class="legend-item"><span class="legend-box sub"></span> มีคนแทน (Substitute)</div>
            <div class="legend-item"><span class="legend-box close"></span> ปิด/สิ้นสุดเวลา</div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">เคาน์เตอร์</th>
                        <?php foreach($weekDays as $day): ?><th>{{ $day->translatedFormat('D j M') }}</th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($counters as $counter): ?>
                        <tr>
                            <td>
                                <div class="counter-title">เคาน์เตอร์ {{ $counter->counter_id }}</div>
                                <div class="counter-desc">{{ $counter->counter_location }}</div>
                            </td>
                            <?php foreach($weekDays as $day): ?>
                                <td>
                                    <?php 
                                    $primaryIds = isset($filteredSchedulesCollection) ? $filteredSchedulesCollection->pluck('schedule_id')->toArray() : [];
                                    foreach($counter->countersubs as $sub): 
                                        foreach($sub->schedules as $sch): 
                                            $isPrimary = empty($primaryIds) ? true : in_array($sch->schedule_id, $primaryIds);
                                            if($sch->schedule_date == $day->format('Y-m-d') && $isPrimary):
                                                $rawStatus = strtolower($sch->status ?? 'empty');
                                                $subArray = isset($substitutesData[$sch->schedule_id]) ? json_decode($substitutesData[$sch->schedule_id], true) : [];
                                                $hasSubs = !empty($subArray);
                                                
                                                $slotEndDT = \Carbon\Carbon::parse($sch->schedule_date . ' ' . $sch->end_time);
                                                $isPastSlot = \Carbon\Carbon::now()->gt($slotEndDT);

                                                $cardClass = 'waiting-card';
                                                $timeClass = 'waiting-text';
                                                $nameClass = '';

                                                if ($rawStatus === 'sick') {
                                                    $cardClass = $hasSubs ? 'sub-card' : 'sick-card';
                                                    $timeClass = 'sick-text';
                                                    $nameClass = 'sick-line';
                                                } elseif ($rawStatus === 'checkin') {
                                                    $cardClass = 'checkin-card';
                                                    $timeClass = '';
                                                } elseif ($rawStatus === 'close' || $isPastSlot) {
                                                    $cardClass = 'close-card';
                                                    $timeClass = 'close-text';
                                                }
                                    ?>
                                                <div class="schedule-card {{ $cardClass }}"
                                                onclick='openComplexEditModal({{ $sch->schedule_id }}, "{{ $counter->counter_id }}", "{{ $sub->counter_sub_id }}", "{{ $day->translatedFormat("l") }}", "{{ $sch->start_time }}", "{{ $sch->end_time }}", "{{ $sch->schedule_date }}", "{{ $sch->staff->staff_name ?? "ไม่ระบุ" }}", {{ $sch->staff_id ?? 0 }}, "{{ $sch->status }}", {!! $substitutesData[$sch->schedule_id] ?? "[]" !!}, "{{ $sch->created_at ? \Carbon\Carbon::parse($sch->created_at)->format('d/m/Y H:i') : '' }}", "{{ $sch->updated_at ? \Carbon\Carbon::parse($sch->updated_at)->format('d/m/Y H:i') : '' }}")'>
                                                    <div class="sch-time {{ $timeClass }}">{{ substr($sch->start_time,0,5) }} - {{ substr($sch->end_time,0,5) }}</div>
                                                    <div class="sch-name {{ $nameClass }}">{{ $sch->staff->staff_name ?? 'ไม่ระบุชื่อ' }}</div>
                                                    <?php if($hasSubs): ?>
                                                        <div style="font-size: 11px; color: #6b46c1; font-weight: 600; margin-top: 3px;">
                                                            <i class="fas fa-user-shield"></i> แทนโดย: {{ collect($subArray)->pluck('name')->implode(', ') }}
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    endforeach; 
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- Modal: รายเคาน์เตอร์ & Subcounter -->
    <!-- ============================================== -->
    <div id="counterViewModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 650px; max-height: 85vh; overflow-y: auto;">
            <div class="modal-header">
                <div>
                    <h2 id="counterModalTitle" style="margin-bottom: 5px;">รายการเคาน์เตอร์ทั้งหมด</h2>
                    <p id="counterModalSubTitle" style="color: #718096; font-size: 13px;">คลิกเลือกเคาน์เตอร์เพื่อดูจุดบริการย่อย (Subcounter) ภายในเคาน์เตอร์นั้น</p>
                </div>
                <button type="button" class="close-btn" onclick="closeModal('counterViewModal')">&times;</button>
            </div>

            <div id="mainCounterListView">
                <div class="counter-modal-grid">
                    <?php foreach($counters as $counter): ?>
                        <div class="counter-item-card" onclick="viewSubCounters('{{ $counter->counter_id }}', '{{ $counter->counter_location }}')">
                            <div>
                                <div style="font-weight: 700; font-size: 15px; color: #1a202c;">เคาน์เตอร์ {{ $counter->counter_id }}</div>
                                <div style="font-size: 12px; color: #718096; margin-top: 3px;">{{ $counter->counter_location }}</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="counter-badge">{{ $counter->countersubs->count() }} จุดบริการย่อย</span>
                                <i class="fas fa-chevron-right" style="color: #cbd5e0; font-size: 12px;"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="subCounterDetailView" style="display: none;">
                <div style="margin-bottom: 15px;">
                    <button type="button" class="btn btn-outline" onclick="backToMainCounters()" style="padding: 6px 14px; font-size: 13px;">
                        <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> กลับหน้ารวมเคาน์เตอร์
                    </button>
                </div>

                <?php foreach($counters as $counter): ?>
                    <div id="modal_sub_container_{{ $counter->counter_id }}" class="modal-sub-group" style="display: none;">
                        <?php 
                        $idx = 1;
                        foreach($counter->countersubs as $sub): 
                        ?>
                            <div class="subcounter-detail-card">
                                <div class="subcounter-header">
                                    <div>
                                        <span style="font-weight: 700; font-size: 14px; color: #1a202c;">จุดบริการย่อยที่ {{ $idx++ }}</span>
                                        <span style="font-size: 12px; color: #805ad5; font-weight: 600; margin-left: 6px;">(SubCounter ID: {{ $sub->counter_sub_id }})</span>
                                    </div>
                                    <span class="counter-badge" style="background: #ebf8ff; color: #2b6cb0;">
                                        ตารางสัปดาห์นี้ {{ $sub->schedules->count() }} รายการ
                                    </span>
                                </div>
                                <div>
                                    <?php if($sub->schedules->count() > 0): ?>
                                        <div style="font-size: 12px; color: #718096; margin-bottom: 6px;">บุคลากรที่ปฏิบัติงานในจุดบริการย่อยนี้:</div>
                                        <?php foreach($sub->schedules as $sch): ?>
                                            <span class="sub-sch-tag">
                                                <i class="far fa-calendar-alt"></i> {{ \Carbon\Carbon::parse($sch->schedule_date)->translatedFormat('D j M') }}
                                                ({{ substr($sch->start_time,0,5) }}-{{ substr($sch->end_time,0,5) }})
                                                • <strong>{{ $sch->staff->staff_name ?? 'ไม่ระบุ' }}</strong>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="font-size: 12px; color: #a0aec0;">ยังไม่มีตารางปฏิบัติงานในสัปดาห์นี้</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if($counter->countersubs->count() == 0): ?>
                            <div style="text-align: center; color: #a0aec0; padding: 30px 0;">ไม่มีข้อมูล Subcounter ในเคาน์เตอร์นี้</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="modal-footer" style="margin-top: 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('counterViewModal')">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- Modal: Import Excel -->
    <!-- ============================================== -->
    <div id="importModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <div>
                    <h2 style="margin-bottom: 5px;">Import ตารางปฏิบัติงาน</h2>
                    <p style="color: #718096; font-size: 13px;">อัปโหลดไฟล์ตารางเวรเพื่อเพิ่มข้อมูลรวดเดียวเข้า Database</p>
                </div>
                <button type="button" class="close-btn" onclick="closeModal('importModal')">&times;</button>
            </div>

            <div style="background: #ebf8ff; border: 1px solid #bee3f8; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <div style="color: #2b6cb0; font-weight: 700; font-size: 13px; margin-bottom: 5px;"><i class="fas fa-info-circle"></i> รูปแบบคอลัมน์ที่รองรับ (ต้องมีบรรทัด Header)</div>
                <div style="color: #3182ce; font-size: 12px; line-height: 1.6;">
                    กรุณาเซฟไฟล์จาก Excel เป็นนามสกุล <strong>CSV UTF-8 (Comma delimited) (*.csv)</strong><br>
                    <strong>A:</strong> รหัสเคาน์เตอร์ย่อย (Counter Sub ID)<br>
                    <strong>B:</strong> รหัสบุคลากร (Staff ID)<br>
                    <strong>C:</strong> วันที่เข้ากะ (YYYY-MM-DD เช่น 2026-09-30)<br>
                    <strong>D:</strong> เวลาเริ่มงาน (HH:MM เช่น 08:30)<br>
                    <strong>E:</strong> เวลาเลิกงาน (HH:MM เช่น 12:00)
                </div>
            </div>

            <form action="{{ route('schedule.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label class="form-label">เลือกไฟล์ (.csv)</label>
                    <input type="file" name="import_file" class="form-control" accept=".csv" required style="padding: 10px;">
                </div>
                
                <div class="modal-footer" style="margin-top: 30px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('importModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">ยืนยันการ Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- Modal: เพิ่มตารางปฏิบัติงาน -->
    <!-- ============================================== -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 650px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <div>
                    <h2 style="margin-bottom: 5px;">เพิ่มตารางปฏิบัติงาน</h2>
                    <p style="color: #718096; font-size: 13px;">กำหนดเวลาได้อิสระ — คน 1 คนอยู่หลายเคาน์เตอร์ได้ถ้าคนละช่วงเวลา</p>
                </div>
                <button type="button" class="close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            
            <div class="stepper-container">
                <div class="step-item active" id="stepIndicator1">1. เลือกเคาน์เตอร์</div>
                <div class="step-item" id="stepIndicator2">2. กำหนดเวลา</div>
                <div class="step-item" id="stepIndicator3">3. มอบหมายคน</div>
            </div>

            <form action="{{ route('schedule.store') }}" method="POST" id="addScheduleForm">
                @csrf
                
                <div id="stepContent1" class="step-content active">
                    <div style="font-weight: 700; font-size: 14px; color: #1a202c; margin-bottom: 15px;">1. เลือกเคาน์เตอร์หลัก</div>
                    <div class="radio-card-grid">
                        <?php foreach($counters as $counter): ?>
                            <label class="radio-card" onclick="selectMainCounter('{{ $counter->counter_id }}', 'เคาน์เตอร์ {{ $counter->counter_id }}')">
                                <input type="radio" name="main_counter_id" value="{{ $counter->counter_id }}" id="main_counter_{{ $counter->counter_id }}" required>
                                <div>
                                    <div style="font-weight: 700; color: #1a202c; font-size: 14px;">เคาน์เตอร์ {{ $counter->counter_id }}</div>
                                    <div style="font-size: 12px; color: #718096; margin-top: 2px;">{{ $counter->counter_location }}</div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div id="subCounterSection" style="display: none; margin-top: 25px; padding-top: 20px; border-top: 1px dashed #cbd5e0;">
                        <div style="font-weight: 700; font-size: 14px; color: #1a202c; margin-bottom: 15px;">2. เลือกเคาน์เตอร์ย่อย <span id="selectedMainCounterLabel" style="color: #8b5cf6;"></span></div>
                        
                        <?php foreach($counters as $counter): ?>
                            <div id="sub_list_{{ $counter->counter_id }}" class="sub-counter-list" style="display: none;">
                                <?php 
                                $subCount = 1;
                                foreach($counter->countersubs as $sub): 
                                ?>
                                    <label class="radio-card" onclick="updateSummary()">
                                        <input type="radio" name="counter_sub_id" value="{{ $sub->counter_sub_id }}" required>
                                        <div>
                                            <div style="font-weight: 700; color: #1a202c; font-size: 14px;">จุดบริการที่ {{ $subCount++ }}</div>
                                            <div style="font-size: 12px; color: #718096; margin-top: 2px;">(Sub ID: {{ $sub->counter_sub_id }})</div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                                
                                <?php if($counter->countersubs->count() == 0): ?>
                                    <div style="font-size: 13px; color: #e53e3e; padding: 10px;">ไม่มีเคาน์เตอร์ย่อยในระบบ กรุณาติดต่อแอดมิน</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="modal-footer" style="margin-top: 25px;">
                        <button type="button" class="btn btn-outline" onclick="closeAddModal()">ยกเลิก</button>
                        <button type="button" class="btn btn-yellow" onclick="nextStep(2)">ถัดไป</button>
                    </div>
                </div>

                <div id="stepContent2" class="step-content">
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">เวลาเริ่ม</label><input type="time" name="start_time" id="add_start_time" class="form-control" onchange="updateSummary(); validateAddScheduleConflicts();" required></div>
                        <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">เวลาสิ้นสุด</label><input type="time" name="end_time" id="add_end_time" class="form-control" onchange="updateSummary(); validateAddScheduleConflicts();" required></div>
                    </div>
                    
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">วันที่เริ่ม</label><input type="date" name="schedule_date" id="add_schedule_date" class="form-control" onchange="toggleEndDate(); updateSummary(); validateAddScheduleConflicts();" required></div>
                        <div class="form-group" style="flex: 1; margin: 0;" id="endDateContainer"><label class="form-label">วันที่สิ้นสุด (ถ้าหลายสัปดาห์)</label><input type="date" name="end_date" id="add_end_date" class="form-control" onchange="updateSummary(); validateAddScheduleConflicts();"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">วันที่ทำงาน</label>
                        <div class="day-selector">
                            <label class="day-btn" id="lbl_day_1"><input type="checkbox" name="work_days[]" value="1" style="display:none;" onchange="toggleDayBtn(this)">จ</label>
                            <label class="day-btn" id="lbl_day_2"><input type="checkbox" name="work_days[]" value="2" style="display:none;" onchange="toggleDayBtn(this)">อ</label>
                            <label class="day-btn" id="lbl_day_3"><input type="checkbox" name="work_days[]" value="3" style="display:none;" onchange="toggleDayBtn(this)">พ</label>
                            <label class="day-btn" id="lbl_day_4"><input type="checkbox" name="work_days[]" value="4" style="display:none;" onchange="toggleDayBtn(this)">พฤ</label>
                            <label class="day-btn" id="lbl_day_5"><input type="checkbox" name="work_days[]" value="5" style="display:none;" onchange="toggleDayBtn(this)">ศ</label>
                            <label class="day-btn" id="lbl_day_6"><input type="checkbox" name="work_days[]" value="6" style="display:none;" onchange="toggleDayBtn(this)">ส</label>
                            <label class="day-btn" id="lbl_day_0"><input type="checkbox" name="work_days[]" value="0" style="display:none;" onchange="toggleDayBtn(this)">อา</label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="prevStep(1)">กลับ</button>
                        <button type="button" class="btn btn-yellow" onclick="nextStep(3)">ถัดไป</button>
                    </div>
                </div>

                <div id="stepContent3" class="step-content">
                    <div class="summary-box">
                        <div class="summary-item"><div class="label">เคาน์เตอร์</div><div class="value" id="summary_counter">-</div></div>
                        <div class="summary-item"><div class="label">เวลา</div><div class="value" id="summary_time">-</div></div>
                        <div class="summary-item"><div class="label">วัน</div><div class="value" id="summary_date">-</div></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" style="font-size: 14px;">บุคลากรหลัก (Primary)</label>
                        <select name="staff_id" id="add_primary_staff" class="form-control" required style="font-weight: 500;" onchange="validateAddScheduleConflicts()">
                            <option value="">— เลือกบุคลากร —</option>
                            <?php foreach($staffs as $staff): ?>
                                <option value="{{ $staff->staff_id }}">{{ $staff->staff_name }} (BUU-{{ str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) }})</option>
                            <?php endforeach; ?>
                        </select>

                        <div id="primaryConflictAlert" class="conflict-alert-box">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div id="primaryConflictText"></div>
                        </div>

                        <div class="field-hint">
                            <i class="fas fa-random" style="color: #a0aec0;"></i> คนเดียวกันอยู่หลายเคาน์เตอร์ได้ถ้าคนละช่วงเวลา
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-size: 14px; display: flex; align-items: center; gap: 8px;">
                            บุคลากรสำรอง (Substitute) <span style="background: #faf5ff; color: #805ad5; font-size: 11px; padding: 2px 6px; border-radius: 4px; border: 1px solid #d6bcfa;">ไม่บังคับ — เพิ่มทีหลังได้</span>
                        </label>
                        <select name="substitute_staff_id" id="add_sub_staff" class="form-control" style="font-weight: 500;" onchange="validateAddScheduleConflicts()">
                            <option value="">— ไม่กำหนดสำรอง —</option>
                            <?php foreach($staffs as $staff): ?>
                                <option value="{{ $staff->staff_id }}">{{ $staff->staff_name }} (BUU-{{ str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) }})</option>
                            <?php endforeach; ?>
                        </select>

                        <div id="subConflictAlert" class="conflict-alert-box">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div id="subConflictText"></div>
                        </div>

                        <div class="field-hint">
                            <i class="far fa-star" style="color: #a0aec0;"></i> ถ้ากำหนดไว้ล่วงหน้า เมื่อคนหลักลา &#8594; เรียกคนสำรองเข้าแทนได้ทันที
                        </div>
                    </div>

                    <div id="bottomConflictBanner" class="conflict-bottom-banner">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="bottomConflictText"></span>
                    </div>

                    <div class="modal-footer" style="margin-top: 25px; border-top: 1px solid #edf2f7; padding-top: 20px;">
                        <button type="button" class="btn btn-outline" onclick="prevStep(2)">&#8592; กลับ</button>
                        <button type="submit" id="btnSubmitAddSchedule" class="btn btn-yellow">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- Modal: จัดการตาราง (Edit / Substitute / History) -->
    <!-- ============================================== -->
    <div id="complexEditModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 550px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <div>
                    <h2 id="modalEditTitle" style="margin-bottom: 5px;">แก้ไข เคาน์เตอร์ X — วัน...</h2>
                    <p id="modalEditSubTitle" style="color: #718096; font-size: 13px;">ชั้น X • 08:00–12:00</p>
                </div>
                <button class="close-btn" onclick="closeModal('complexEditModal')">&times;</button>
            </div>

            <div class="modal-tabs-header">
                <div class="m-tab active" onclick="switchEditTab(1)">ข้อมูล Slot</div>
                <div class="m-tab" onclick="switchEditTab(2)">คนแทน / ลาป่วย</div>
                <div class="m-tab" onclick="switchEditTab(3)">ประวัติ</div>
            </div>

            <div id="eTab1" class="m-tab-content active">
                <form id="complexEditForm" method="POST">
                    @csrf
                    <div class="primary-staff-card" id="primaryStaffCardBox">
                        <div>
                            <div style="font-weight: 700; font-size: 16px; color: #1a202c;" id="displayStaffName">ชื่อบุคลากร</div>
                            <div style="font-size: 13px; color: #718096; margin-top: 2px;" id="displayStaffCode">BUU-000 • บุคลากรหลัก</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #38a169; font-weight: 600; font-size: 14px;" id="displayStatusText">รอ Check-in</div>
                            <div style="font-size: 12px; color: #718096; margin-top: 2px;" id="displayStaffTime">08:00–12:00</div>
                        </div>
                    </div>

                    <div style="border: 1px solid #edf2f7; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <div style="font-weight: 600; margin-bottom: 15px;">เวลาทำงาน</div>
                        <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">เวลาเริ่ม</label><input type="time" name="start_time" id="c_edit_start" class="form-control" required></div>
                            <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">เวลาสิ้นสุด</label><input type="time" name="end_time" id="c_edit_end" class="form-control" required></div>
                        </div>
                        <input type="hidden" name="schedule_date" id="c_edit_date">
                        <input type="hidden" name="staff_id" id="c_edit_staff">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('complexEditModal')">ยกเลิก</button>
                        <button type="submit" class="btn btn-yellow">บันทึก</button>
                    </div>
                </form>
                
                <form id="cDeleteForm" method="POST" style="margin-top: 20px;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบ Slot นี้?')">
                        <div>
                            <div style="font-size: 15px; text-align: left;">ลบ Slot นี้</div>
                            <div style="font-size: 12px; font-weight: 400; margin-top: 2px;">ลบออกจากตาราง</div>
                        </div>
                        <i class="far fa-trash-alt"></i>
                    </button>
                </form>
            </div>
            
            <div id="eTab2" class="m-tab-content">
                <div class="sick-alert-box">
                    <h4 style="color: #e53e3e; margin-bottom: 8px; font-size: 15px; font-weight: 700;">บุคลากรหลักไม่สามารถมาได้?</h4>
                    <button type="button" id="btnToggleSick" class="btn-toggle-sick" onclick="toggleSick()">ทำเครื่องหมาย "ลาป่วย / ไม่มา"</button>
                </div>

                <div id="substitutesListContainer">
                    <div style="font-weight: 700; font-size: 14px; color: #1a202c; margin-bottom: 15px;">คนแทนที่ Assign แล้ว ( <span id="subCount">0</span> )</div>
                    <div id="subsListHtml"></div>
                    <div style="border-top: 1px dashed #cbd5e0; margin: 25px 0;"></div>

                    <div style="font-weight: 700; font-size: 14px; color: #1a202c; margin-bottom: 15px; display: flex; align-items: center;"><i class="fas fa-user-plus" style="color: #805ad5; margin-right: 8px;"></i> เพิ่มคนแทนอีกคน</div>
                    
                    <div class="suggested-sub-card" id="suggestedSubCard" style="display: none;">
                        <div>
                            <div style="font-weight: 700; color: #1a202c; font-size: 14px;" id="suggestedSubName">นาย ... (BUU-...)</div>
                            <div style="font-size: 12px; color: #6b46c1; margin-top: 2px;">บุคลากรสำรองที่กำหนดล่วงหน้า - ว่างช่วงนี้ <i class="fas fa-check"></i></div>
                        </div>
                        <button type="button" class="btn-call-sub" onclick="selectSuggestedSub()">เรียก</button>
                    </div>

                    <form action="{{ route('schedule.store') }}" method="POST">
                        @csrf
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label class="form-label">เลือกคนแทน</label>
                            <select name="staff_id" id="subSelectBox" class="form-control" required onchange="validateEditSubConflict()">
                                <option value="">— เลือกบุคลากร —</option>
                                <?php foreach($staffs as $staff): ?>
                                    <option value="{{ $staff->staff_id }}">{{ $staff->staff_name }} (BUU-{{ str_pad($staff->staff_id, 3, '0', STR_PAD_LEFT) }})</option>
                                <?php endforeach; ?>
                            </select>
                            <div id="editSubConflictAlert" class="conflict-alert-box">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div id="editSubConflictText"></div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">เริ่มแทน</label><input type="time" name="start_time" id="new_sub_start" class="form-control" required onchange="validateEditSubConflict()"></div>
                            <div class="form-group" style="flex: 1; margin: 0;"><label class="form-label">ถึงเวลา</label><input type="time" name="end_time" id="new_sub_end" class="form-control" required onchange="validateEditSubConflict()"></div>
                        </div>
                        <input type="hidden" name="counter_sub_id" id="new_sub_counter">
                        <input type="hidden" name="schedule_date" id="new_sub_date">
                        <input type="hidden" name="status" value="empty">
                        <button type="submit" id="btnSubmitEditSub" class="btn btn-yellow" style="width: 100%; justify-content: center; padding: 12px 0;">ยืนยัน Assign คนแทน</button>
                    </form>
                </div>
                
                <form id="sickUpdateForm" method="POST" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    @csrf
                    <input type="hidden" name="staff_id" id="s_edit_staff">
                    <input type="hidden" name="schedule_date" id="s_edit_date">
                    <input type="hidden" name="start_time" id="s_edit_start">
                    <input type="hidden" name="end_time" id="s_edit_end">
                    <input type="hidden" name="status" id="s_edit_status" value="sick">
                    <button type="button" class="btn btn-outline" onclick="closeModal('complexEditModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-yellow">บันทึก</button>
                </form>
            </div>

            <div id="eTab3" class="m-tab-content">
                <div id="historyTimelineContainer">
                    <div style="color: #718096; font-size: 14px; text-align: center; margin-top: 30px;">ยังไม่มีประวัติการเปลี่ยนแปลง</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts ควบคุมระบบทั้งหมด -->
    <script>
        const allStaffsList = {!! isset($staffAvailability) ? json_encode($staffAvailability) : '[]' !!};

        function openModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function openCounterListModal() {
            backToMainCounters();
            openModal('counterViewModal');
        }

        function viewSubCounters(counterId, counterLocation) {
            document.getElementById('mainCounterListView').style.display = 'none';
            document.getElementById('subCounterDetailView').style.display = 'block';
            
            document.getElementById('counterModalTitle').innerText = 'เคาน์เตอร์ ' + counterId + ' — จุดบริการย่อย';
            document.getElementById('counterModalSubTitle').innerText = counterLocation;

            document.querySelectorAll('.modal-sub-group').forEach(el => el.style.display = 'none');
            let target = document.getElementById('modal_sub_container_' + counterId);
            if(target) {
                target.style.display = 'block';
            }
        }

        function backToMainCounters() {
            document.getElementById('subCounterDetailView').style.display = 'none';
            document.getElementById('mainCounterListView').style.display = 'block';
            document.getElementById('counterModalTitle').innerText = 'รายการเคาน์เตอร์ทั้งหมด';
            document.getElementById('counterModalSubTitle').innerText = 'คลิกเลือกเคาน์เตอร์เพื่อดูจุดบริการย่อย (Subcounter) ภายในเคาน์เตอร์นั้น';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            setTimeout(() => {
                showStep(1);
                document.getElementById('addScheduleForm').reset();
                document.querySelectorAll('.day-btn').forEach(b => b.classList.remove('active'));
                document.getElementById('subCounterSection').style.display = 'none';
                clearAddConflictAlerts();
            }, 300);
        }

        function selectMainCounter(counterId, counterName) {
            document.querySelectorAll('.sub-counter-list').forEach(el => el.style.display = 'none');
            document.querySelectorAll('input[name="counter_sub_id"]').forEach(el => el.checked = false);
            
            let subList = document.getElementById('sub_list_' + counterId);
            if(subList) {
                subList.style.display = 'grid'; 
                document.getElementById('subCounterSection').style.display = 'block';
                document.getElementById('selectedMainCounterLabel').innerText = '— ' + counterName;
            }
            updateSummary();
        }

        function nextStep(step) {
            if (step === 2) {
                let checkedMain = document.querySelector('input[name="main_counter_id"]:checked');
                let checkedSub = document.querySelector('input[name="counter_sub_id"]:checked');
                if (!checkedMain) { alert("กรุณาเลือกเคาน์เตอร์หลักก่อนครับ"); return; }
                if (!checkedSub) { alert("กรุณาเลือกเคาน์เตอร์ย่อยด้วยครับ"); return; }
            }
            if (step === 3) {
                let start = document.getElementById('add_start_time').value;
                let end = document.getElementById('add_end_time').value;
                let date = document.getElementById('add_schedule_date').value;
                if (!start || !end || !date) { alert("กรุณากรอกเวลาและวันที่ให้ครบถ้วนครับ"); return; }
                if (start >= end) { alert("เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มครับ"); return; }
                
                let anyDayChecked = document.querySelector('input[name="work_days[]"]:checked');
                if (!anyDayChecked) { alert("กรุณาเลือกวันที่ทำงานอย่างน้อย 1 วันครับ"); return; }
                
                validateAddScheduleConflicts();
            }
            showStep(step);
        }

        function prevStep(step) { showStep(step); }

        function showStep(step) {
            document.querySelectorAll('#addModal .step-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('#addModal .step-item').forEach(el => el.classList.remove('active'));
            document.getElementById('stepContent' + step).classList.add('active');
            document.getElementById('stepIndicator' + step).classList.add('active');
        }

        function toggleDayBtn(checkbox) {
            let label = checkbox.parentElement;
            if(checkbox.checked) { label.classList.add('active'); } 
            else { label.classList.remove('active'); }
            updateSummary();
            validateAddScheduleConflicts();
        }

        function toggleEndDate() {
            let startDateVal = document.getElementById('add_schedule_date').value;
            if(startDateVal) {
                let d = new Date(startDateVal);
                let dayOfWeek = d.getDay();
                
                document.querySelectorAll('.day-btn').forEach(b => {
                    b.classList.remove('active');
                    b.querySelector('input').checked = false;
                });
                
                let targetCheckbox = document.querySelector('input[name="work_days[]"][value="'+dayOfWeek+'"]');
                if(targetCheckbox) {
                    targetCheckbox.checked = true;
                    targetCheckbox.parentElement.classList.add('active');
                }
                
                let endDateInput = document.getElementById('add_end_date');
                endDateInput.disabled = false;
                endDateInput.min = startDateVal;
            }
        }

        function updateSummary() {
            let checkedMain = document.querySelector('input[name="main_counter_id"]:checked');
            let checkedSub = document.querySelector('input[name="counter_sub_id"]:checked');
            
            if (checkedMain) {
                let mainName = checkedMain.nextElementSibling.querySelector('div').innerText;
                if(checkedSub) {
                    let subName = checkedSub.nextElementSibling.querySelector('div').innerText;
                    document.getElementById('summary_counter').innerText = mainName + " (" + subName + ")";
                } else {
                    document.getElementById('summary_counter').innerText = mainName;
                }
            }

            let start = document.getElementById('add_start_time').value;
            let end = document.getElementById('add_end_time').value;
            if (start && end) {
                document.getElementById('summary_time').innerText = start + "–" + end;
            }

            let startDateVal = document.getElementById('add_schedule_date').value;
            let endDateVal = document.getElementById('add_end_date').value;
            
            if (startDateVal) {
                let sDate = new Date(startDateVal);
                let dateText = sDate.getDate() + '/' + (sDate.getMonth()+1) + '/' + sDate.getFullYear();
                
                if (endDateVal && endDateVal !== startDateVal) {
                    let eDate = new Date(endDateVal);
                    dateText += " ถึง " + eDate.getDate() + '/' + (eDate.getMonth()+1) + '/' + eDate.getFullYear();
                }
                
                let checkedDays = Array.from(document.querySelectorAll('input[name="work_days[]"]:checked')).map(cb => cb.parentElement.innerText.trim());
                if(checkedDays.length > 0) {
                    dateText += " (ทุกวัน " + checkedDays.join(', ') + ")";
                }

                document.getElementById('summary_date').innerText = dateText;
            }
        }

        function getTargetScheduleDates() {
            let startDateVal = document.getElementById('add_schedule_date').value;
            let endDateVal = document.getElementById('add_end_date').value || startDateVal;
            if (!startDateVal) return [];

            let checkedDays = Array.from(document.querySelectorAll('input[name="work_days[]"]:checked')).map(cb => parseInt(cb.value));
            let dates = [];
            let curr = new Date(startDateVal);
            let end = new Date(endDateVal);

            while (curr <= end) {
                if (checkedDays.length === 0 || checkedDays.includes(curr.getDay())) {
                    let yyyy = curr.getFullYear();
                    let mm = String(curr.getMonth() + 1).padStart(2, '0');
                    let dd = String(curr.getDate()).padStart(2, '0');
                    dates.push(`${yyyy}-${mm}-${dd}`);
                }
                curr.setDate(curr.getDate() + 1);
            }
            return dates;
        }

        function findStaffConflict(staffId, targetDates, startTime, endTime) {
            if (!staffId || !startTime || !endTime || targetDates.length === 0) return null;
            let staffObj = allStaffsList.find(s => String(s.id) === String(staffId));
            if (!staffObj || !staffObj.schedules) return null;

            for (let sch of staffObj.schedules) {
                if (targetDates.includes(sch.date)) {
                    if (sch.start < endTime && sch.end > startTime) {
                        return {
                            staffName: staffObj.name,
                            counterId: sch.counter_id,
                            start: sch.start,
                            end: sch.end,
                            date: sch.date
                        };
                    }
                }
            }
            return null;
        }

        function clearAddConflictAlerts() {
            document.getElementById('primaryConflictAlert').style.display = 'none';
            document.getElementById('subConflictAlert').style.display = 'none';
            document.getElementById('bottomConflictBanner').style.display = 'none';
            document.getElementById('add_primary_staff').style.borderColor = '#cbd5e0';
            document.getElementById('add_sub_staff').style.borderColor = '#cbd5e0';
            document.getElementById('btnSubmitAddSchedule').disabled = false;
        }

        function validateAddScheduleConflicts() {
            clearAddConflictAlerts();

            let primaryId = document.getElementById('add_primary_staff').value;
            let subId = document.getElementById('add_sub_staff').value;
            let startTime = document.getElementById('add_start_time').value;
            let endTime = document.getElementById('add_end_time').value;
            let targetDates = getTargetScheduleDates();

            let hasConflict = false;
            let bannerMessage = '';

            if (primaryId) {
                let pConflict = findStaffConflict(primaryId, targetDates, startTime, endTime);
                if (pConflict) {
                    hasConflict = true;
                    document.getElementById('primaryConflictAlert').style.display = 'flex';
                    document.getElementById('primaryConflictText').innerHTML = 
                        `<strong>Conflict! ${pConflict.staffName} มี slot ที่เคาน์เตอร์ ${pConflict.counterId} ช่วงเวลาเดียวกัน (${pConflict.start}–${pConflict.end})</strong><br>— กรุณาเปลี่ยนเวลาหรือเลือกคนอื่น`;
                    document.getElementById('add_primary_staff').style.borderColor = '#fc8181';
                    bannerMessage = `Conflict: ${pConflict.staffName} มี slot ซ้อนทับช่วงเวลาเดียวกันที่เคาน์เตอร์ ${pConflict.counterId}`;
                }
            }

            if (subId) {
                if (primaryId && subId === primaryId) {
                    hasConflict = true;
                    document.getElementById('subConflictAlert').style.display = 'flex';
                    document.getElementById('subConflictText').innerHTML = 
                        `<strong>Conflict! บุคลากรสำรองต้องไม่ใช่คนเดียวกับบุคลากรหลัก</strong><br>— กรุณาเลือกบุคลากรสำรองคนอื่น`;
                    document.getElementById('add_sub_staff').style.borderColor = '#fc8181';
                    if (!bannerMessage) bannerMessage = `Conflict: บุคลากรสำรองซ้ำกับบุคลากรหลัก`;
                } else {
                    let sConflict = findStaffConflict(subId, targetDates, startTime, endTime);
                    if (sConflict) {
                        hasConflict = true;
                        document.getElementById('subConflictAlert').style.display = 'flex';
                        document.getElementById('subConflictText').innerHTML = 
                            `<strong>Conflict! ${sConflict.staffName} มี slot ที่เคาน์เตอร์ ${sConflict.counterId} ช่วงเวลาเดียวกัน (${sConflict.start}–${sConflict.end})</strong><br>— กรุณาเปลี่ยนเวลาหรือเลือกคนสำรองอื่น`;
                        document.getElementById('add_sub_staff').style.borderColor = '#fc8181';
                        if (!bannerMessage) bannerMessage = `Conflict: ${sConflict.staffName} มี slot ซ้อนทับช่วงเวลาเดียวกันที่เคาน์เตอร์ ${sConflict.counterId}`;
                    }
                }
            }

            if (hasConflict) {
                document.getElementById('bottomConflictBanner').style.display = 'flex';
                document.getElementById('bottomConflictText').innerText = bannerMessage;
                document.getElementById('btnSubmitAddSchedule').disabled = true;
            }
        }

        function validateEditSubConflict() {
            let subId = document.getElementById('subSelectBox').value;
            let dateVal = document.getElementById('new_sub_date').value;
            let startVal = document.getElementById('new_sub_start').value;
            let endVal = document.getElementById('new_sub_end').value;

            let alertBox = document.getElementById('editSubConflictAlert');
            let alertText = document.getElementById('editSubConflictText');
            let btnSubmit = document.getElementById('btnSubmitEditSub');
            let selectBox = document.getElementById('subSelectBox');

            alertBox.style.display = 'none';
            selectBox.style.borderColor = '#cbd5e0';
            btnSubmit.disabled = false;

            if (!subId || !dateVal || !startVal || !endVal) return;

            let conflict = findStaffConflict(subId, [dateVal], startVal, endVal);
            if (conflict) {
                alertBox.style.display = 'flex';
                alertText.innerHTML = `<strong>Conflict! ${conflict.staffName} มี slot ที่เคาน์เตอร์ ${conflict.counterId} ช่วงเวลาเดียวกัน (${conflict.start}–${conflict.end})</strong><br>— ไม่สามารถเพิ่มเป็นคนแทนในเวลานี้ได้`;
                selectBox.style.borderColor = '#fc8181';
                btnSubmit.disabled = true;
            }
        }

        function switchEditTab(tabNum) {
            document.querySelectorAll('.m-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.m-tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.m-tab')[tabNum - 1].classList.add('active');
            document.getElementById('eTab' + tabNum).classList.add('active');
        }

        function selectSuggestedSub() {
            let sid = document.getElementById('suggestedSubCard').dataset.staffId;
            let selectBox = document.getElementById('subSelectBox');
            selectBox.value = sid;
            validateEditSubConflict();
            
            selectBox.style.borderColor = '#8b5cf6';
            selectBox.style.boxShadow = '0 0 0 3px rgba(139, 92, 246, 0.2)';
            setTimeout(() => {
                selectBox.style.borderColor = '#cbd5e0';
                selectBox.style.boxShadow = 'none';
            }, 1000);
        }

        // ใช้ค่า 'sick' และ 'empty' ให้ตรงกับ ENUM ในฐานข้อมูลจริง 100%
        function toggleSick() {
            let btn = document.getElementById('btnToggleSick');
            let statusInput = document.getElementById('s_edit_status');

            if (statusInput.value.toLowerCase() !== 'sick') {
                btn.classList.add('active');
                btn.innerText = 'ยกเลิกการลาป่วย (กลับมาทำงาน)';
                statusInput.value = 'sick';
            } else {
                btn.classList.remove('active');
                btn.innerText = 'ทำเครื่องหมาย "ลาป่วย / ไม่มา"';
                statusInput.value = 'empty';
            }
        }

        function openComplexEditModal(schId, counterId, counterSubId, dayName, start, end, date, staffName, staffId, status, substitutes, createdAt, updatedAt) {
            let startShort = start.substring(0, 5);
            let endShort = end.substring(0, 5);
            let normStatus = (status || 'empty').toLowerCase();

            document.getElementById('modalEditTitle').innerText = 'แก้ไข เคาน์เตอร์ ' + counterId + ' — ' + dayName;
            document.getElementById('modalEditSubTitle').innerText = startShort + '–' + endShort;
            
            document.getElementById('displayStaffName').innerText = staffName;
            document.getElementById('displayStaffCode').innerText = 'BUU-' + String(staffId).padStart(3, '0') + ' • บุคลากรหลัก';
            document.getElementById('displayStaffTime').innerText = startShort + '–' + endShort;

            // ตรวจสอบสถานะจาก Backend ร่วมกับวันและเวลาตามความเป็นจริง
            let statusEl = document.getElementById('displayStatusText');
            let cardBox = document.getElementById('primaryStaffCardBox');
            let now = new Date();
            let slotEnd = new Date(date + 'T' + endShort + ':00');

            if (normStatus === 'sick') {
                statusEl.innerText = 'ลาป่วย / ไม่มา';
                statusEl.style.color = '#e53e3e';
                cardBox.style.background = '#fff5f5';
                cardBox.style.borderColor = '#feb2b2';
            } else if (normStatus === 'checkin') {
                statusEl.innerText = 'Check-in อยู่';
                statusEl.style.color = '#38a169';
                cardBox.style.background = '#f0fff4';
                cardBox.style.borderColor = '#9ae6b4';
            } else if (normStatus === 'close') {
                statusEl.innerText = 'ปิด/ไม่เปิดบริการ';
                statusEl.style.color = '#718096';
                cardBox.style.background = '#edf2f7';
                cardBox.style.borderColor = '#cbd5e0';
            } else {
                // กรณีในฐานข้อมูลเป็น 'waiting' หรือ 'empty' (ยังไม่ได้ Check-in)
                if (now > slotEnd) {
                    statusEl.innerText = 'สิ้นสุดเวลา (ไม่ได้ Check-in)';
                    statusEl.style.color = '#718096';
                    cardBox.style.background = '#f7fafc';
                    cardBox.style.borderColor = '#e2e8f0';
                } else {
                    statusEl.innerText = 'มีตาราง รอ Check-in';
                    statusEl.style.color = '#3182ce';
                    cardBox.style.background = '#ebf8ff';
                    cardBox.style.borderColor = '#90cdf4';
                }
            }

            document.getElementById('c_edit_start').value = startShort;
            document.getElementById('c_edit_end').value = endShort;
            document.getElementById('c_edit_date').value = date;
            document.getElementById('c_edit_staff').value = staffId;
            
            document.getElementById('complexEditForm').action = "/schedule/update/" + schId;
            document.getElementById('cDeleteForm').action = "/schedule/delete/" + schId;

            document.getElementById('sickUpdateForm').action = "/schedule/update/" + schId;
            document.getElementById('s_edit_staff').value = staffId;
            document.getElementById('s_edit_date').value = date;
            document.getElementById('s_edit_start').value = startShort;
            document.getElementById('s_edit_end').value = endShort;

            document.getElementById('new_sub_counter').value = counterSubId;
            document.getElementById('new_sub_date').value = date;
            document.getElementById('new_sub_start').value = startShort;
            document.getElementById('new_sub_end').value = endShort;
            document.getElementById('subSelectBox').value = ""; 
            validateEditSubConflict();

            let btnSick = document.getElementById('btnToggleSick');
            
            if(normStatus === 'sick') {
                btnSick.classList.add('active');
                btnSick.innerText = 'ยกเลิกการลาป่วย (กลับมาทำงาน)';
                document.getElementById('s_edit_status').value = 'sick'; 
            } else {
                btnSick.classList.remove('active');
                btnSick.innerText = 'ทำเครื่องหมาย "ลาป่วย / ไม่มา"';
                document.getElementById('s_edit_status').value = 'empty';
            }

            let subHtml = '';
            document.getElementById('subCount').innerText = substitutes.length;
            
            if(substitutes.length > 0) {
                substitutes.forEach(sub => {
                    subHtml += `
                        <div class="substitute-card">
                            <div>
                                <div style="font-weight: 700; color: #1a202c; font-size: 14px;">นาย ${sub.name} (BUU-${String(sub.staff_code).padStart(3, '0')})</div>
                                <div style="font-size: 12px; color: #6b46c1; margin-top: 4px; font-weight: 600;">คุมช่วง ${sub.start}–${sub.end}</div>
                            </div>
                            <form action="/schedule/delete/${sub.id}" method="POST" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background: white; border: 1px solid #e2e8f0; border-radius: 4px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #a0aec0;" onclick="return confirm('ลบคนแทนคนนี้ออกหรือไม่?')"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    `;
                });
            } else {
                subHtml = '<div style="font-size: 13px; color: #a0aec0; text-align: center; margin-bottom: 15px;">ยังไม่ได้ Assign คนแทน</div>';
            }
            document.getElementById('subsListHtml').innerHTML = subHtml;

            let suggestedStaff = allStaffsList.find(s => {
                if (s.id === staffId || substitutes.some(sub => sub.staff_code === s.id)) return false;
                
                let isBusy = s.schedules.some(sch => {
                    if (sch.date !== date) return false;
                    return (sch.start < endShort && sch.end > startShort);
                });
                
                return !isBusy;
            });

            let suggestCard = document.getElementById('suggestedSubCard');
            if(suggestedStaff) {
                suggestCard.style.display = 'flex';
                document.getElementById('suggestedSubName').innerText = suggestedStaff.name + ' (BUU-' + String(suggestedStaff.id).padStart(3, '0') + ')';
                suggestCard.dataset.staffId = suggestedStaff.id; 
            } else {
                suggestCard.style.display = 'none';
            }

            let historyHtml = '<div class="history-list">';
            let hasHistory = false;
            
            if(createdAt) {
                historyHtml += `<div class="h-item"><div class="h-dot create"></div><div><div class="h-time">${createdAt}</div><div class="h-text">สร้างตารางปฏิบัติงาน (คนหลัก)</div></div></div>`;
                hasHistory = true;
            }

            if(updatedAt && updatedAt !== createdAt) {
                historyHtml += `<div class="h-item"><div class="h-dot update"></div><div><div class="h-time">${updatedAt}</div><div class="h-text">อัปเดตข้อมูล / แก้ไขล่าสุด</div></div></div>`;
                hasHistory = true;
            }

            if(substitutes && substitutes.length > 0) {
                substitutes.forEach(sub => {
                    let subTime = sub.created_at || 'ไม่ระบุเวลา';
                    historyHtml += `<div class="h-item"><div class="h-dot sub"></div><div><div class="h-time">${subTime}</div><div class="h-text" style="color:#805ad5;">Assign คนแทน: นาย ${sub.name}</div></div></div>`;
                    hasHistory = true;
                });
            }
            
            historyHtml += '</div>';

            if(!hasHistory) {
                historyHtml = '<div style="color: #718096; font-size: 14px; text-align: center; margin-top: 30px;">ไม่มีข้อมูลประวัติในระบบ</div>';
            }

            document.getElementById('historyTimelineContainer').innerHTML = historyHtml;

            switchEditTab(1); 
            openModal('complexEditModal');
        }
    </script>
</body>
</html>