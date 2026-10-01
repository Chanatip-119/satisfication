<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเคาน์เตอร์</title>
    <link rel="stylesheet" href="{{ asset('css/counter.css') }}?v={{ filemtime(public_path('css/counter.css')) }}">
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Admin Logo">
        </div>

        <div class="menu-category">หลัก</div>
        <a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.*') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>

        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item {{ Route::is('counter.*') ? 'active' : '' }}">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item {{ Route::is('staff.*') ? 'active' : '' }}">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item">QR Code</a>

        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item {{ Route::is('report.export.index') ? 'active' : '' }}">ส่งออกรายงาน</a>

        <a href="{{ url('/') }}" class="logout-btn"></i>↩ Logout</a>
    </aside>

    <main class="content">
        
        <div class="header-row">
            <div class="page-title">
                <h1>จัดการเคาน์เตอร์</h1>
                <p>CRUD Counter Groups และ Sub-Counters</p>
            </div>
            <div class="action-buttons">
                <button class="btn btn-yellow" onclick="openModal('modal-counter', 'add')">+ เพิ่มเคาน์เตอร์</button>
            </div>
        </div>

        @if(session('success'))
            <div style="background: #f0fff4; color: #38a169; padding: 15px; border-radius: 8px; border: 1px solid #c6f6d5; margin-bottom: 20px; font-weight: 500;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div style="background: #fff5f5; color: #e53e3e; padding: 15px; border-radius: 8px; border: 1px solid #fed7d7; margin-bottom: 20px; font-weight: 500;">
                <ul style="margin-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ชื่อเคาน์เตอร์</th>
                        <th>SUB COUNTER</th>
                        <th>ช่วงเวลา</th>
                        <th>QR CODE</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($counters as $counter)
                    <tr>
                        <td>
                            <div class="counter-title">{{ $counter->counter_id }}</div>
                            <div class="counter-desc">{{ $counter->counter_location }}</div>
                        </td>
                        <td>
                            <div class="sub-counter-chips">
                                @forelse($counter->countersubs as $index => $sub)
                                    <span class="chip">{{ $counter->counter_id }}.{{ $index + 1 }}</span>
                                @empty
                                    <span style="color: #a0aec0; font-size: 13px;">ไม่มี Sub-Counter</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 14px; color: #718096; font-weight: 500;">08:00–16:00</span>
                        </td>
                        <td>
                            <button class="btn-generate">Generate</button>
                        </td>
                        <td>
                            @if($counter->is_active)
                                <div class="status active"><div class="status-dot"></div> Active</div>
                            @else
                                <div class="status offline"><div class="status-dot"></div> Close</div>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <button class="icon-btn" onclick="openModal('modal-counter', 'edit', {{ json_encode($counter) }})">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </main>

    <div id="modal-counter" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="modal-title">แก้ไข เคาน์เตอร์</h2>
                    <p id="modal-subtitle">รายละเอียด</p>
                </div>
                <button class="close-btn" onclick="closeModal('modal-counter')">&times;</button>
            </div>

            <div class="tabs">
                <div class="tab active" id="tab-btn-general" onclick="switchTab('general')">ข้อมูลทั่วไป</div>
                <div class="tab" onclick="switchTab('subcounters')" id="tab-subcounter-nav">Sub-Counters</div>
            </div>

            <!-- รวมฟอร์มทั้งหมดเป็นก้อนเดียว -->
            <form action="{{ route('counter.store') }}" method="POST" id="form-unified">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">
                
                <div id="tab-general" class="tab-content active">
                    <div class="form-group">
                        <label class="form-label">ชื่อเคาน์เตอร์ (Group ID)</label>
                        <input type="text" name="counter_id" id="inp_counter_id" class="form-control" placeholder="ระบุชื่อเคาน์เตอร์" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">รายละเอียด / ตำแหน่ง</label>
                        <input type="text" name="counter_location" id="inp_counter_location" class="form-control" placeholder="เช่น ชั้น 1 — บริการทั่วไป" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">สถานะ:</label>
                        <div class="status-radio-group">
                            <label class="status-radio active-green" id="lbl_status_active">
                                <input type="radio" name="is_active" value="1" checked onclick="updateRadioUI(this)">
                                <span style="color:#38a169">●</span> เปิดให้บริการ
                            </label>
                            <label class="status-radio" id="lbl_status_close">
                                <input type="radio" name="is_active" value="0" onclick="updateRadioUI(this)">
                                <span>○</span> ปิดใช้งาน
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer" style="margin-top: 30px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('modal-counter')">ยกเลิก</button>
                        <button type="submit" class="btn btn-yellow">บันทึกข้อมูล</button>
                    </div>
                </div>

                <div id="tab-subcounters" class="tab-content">
                    <div class="sub-counter-header">
                        <span>Sub-Counter คือช่วงเวลาบริการของเคาน์เตอร์นี้</span>
                        <button type="button" class="btn btn-yellow" style="padding: 6px 12px; font-size: 13px;" onclick="addSubCounterField()">+ เพิ่ม Sub</button>
                    </div>
                    
                    <div class="sub-counter-list" id="sub-counter-list-container">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('modal-counter')">ยกเลิก</button>
                        <button type="submit" class="btn btn-yellow">บันทึกข้อมูลทั้งหมด</button>
                    </div>
                </div>
            </form>

            <!-- กล่องลบข้อมูล แยกออกมาเพื่อป้องกัน Form ซ้อน Form -->
            <div id="tab-general-delete" class="tab-content">
                <div class="delete-box" id="delete-section" style="display: none;">
                    <div>
                        <h4>ลบเคาน์เตอร์นี้</h4>
                        <p>ข้อมูลการประเมินทั้งหมดจะถูกลบด้วย</p>
                    </div>
                    <form id="form-delete" action="" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn-delete" onclick="if(confirm('ยืนยันการลบเคาน์เตอร์นี้?')) document.getElementById('form-delete').submit();">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            ลบ
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <form id="form-delete-sub" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <script>
        let currentMaxSubId = 0;

        function openModal(modalId, mode, data = null) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
            currentMaxSubId = 0;
            
            if(mode === 'edit' && data) {
                document.getElementById('modal-title').innerText = 'แก้ไข ' + data.counter_id;
                document.getElementById('modal-subtitle').innerText = data.counter_location;
                
                document.getElementById('inp_counter_id').value = data.counter_id;
                document.getElementById('inp_counter_id').readOnly = true;
                document.getElementById('inp_counter_location').value = data.counter_location;
                
                if(data.is_active) {
                    document.querySelector('input[name="is_active"][value="1"]').click();
                } else {
                    document.querySelector('input[name="is_active"][value="0"]').click();
                }
                
                document.getElementById('form-unified').action = `/counter/${data.counter_id}`;
                document.getElementById('form-method').value = 'PUT';
                
                document.getElementById('delete-section').style.display = 'flex';
                document.getElementById('form-delete').action = `/counter/${data.counter_id}`;
                
                document.getElementById('tab-subcounter-nav').style.display = 'block';
                
                const container = document.getElementById('sub-counter-list-container');
                container.innerHTML = '';
                
                let subIndex = 1;
                
                if(data.countersubs && data.countersubs.length > 0) {
                    data.countersubs.forEach(sub => {
                        const isActive = sub.is_active == 1;
                        const pillBg = isActive ? '' : 'background-color: #fff5f5; color: #e53e3e;';
                        const pillClass = isActive ? 'active' : '';
                        const dotColor = isActive ? '#38a169' : '#e53e3e';
                        const pillText = isActive ? 'เปิดใช้งาน' : 'ปิดใช้งาน';

                        let startTime = '08:00';
                        let endTime = '16:00';
                        if (sub.schedules && sub.schedules.length > 0) {
                            startTime = sub.schedules[0].start_time.substring(0, 5); 
                            endTime = sub.schedules[0].end_time.substring(0, 5);
                        }

                        container.innerHTML += `
                            <div class="sub-counter-item">
                                <div class="sub-id" style="font-size:16px; font-weight:700; color:#FFCC00; width:40px; text-align:center;">
                                    ${data.counter_id}.${subIndex}
                                </div>
                                <div class="time-inputs" style="display:flex; gap:10px; flex:1;">
                                    <input type="hidden" name="existing_sub_id[]" value="${sub.counter_sub_id}">
                                    <div class="form-group" style="margin-bottom:0; flex:1;">
                                        <label style="font-size: 11px; color: #a0aec0;">เวลาเริ่ม</label>
                                        <input type="time" name="existing_start_time[]" class="form-control" value="${startTime}" style="padding: 8px;">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0; flex:1;">
                                        <label style="font-size: 11px; color: #a0aec0;">เวลาสิ้นสุด</label>
                                        <input type="time" name="existing_end_time[]" class="form-control" value="${endTime}" style="padding: 8px;">
                                    </div>
                                </div>
                                <input type="hidden" name="existing_is_active[]" class="sub-is-active-input" value="${sub.is_active ? 1 : 0}">
                                <div class="sub-pill ${pillClass}" style="white-space:nowrap; ${pillBg}">
                                    <span style="color:${dotColor}">●</span> ${pillText}
                                </div>
                                <button type="button" class="icon-btn" onclick="deleteSubCounter('${data.counter_id}', ${sub.counter_sub_id})">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </div>
                        `;
                        subIndex++;
                    });
                }
                currentMaxSubId = subIndex - 1;
                document.getElementById('tab-btn-general').click();

            } else {
                document.getElementById('modal-title').innerText = 'เพิ่มเคาน์เตอร์ใหม่';
                document.getElementById('modal-subtitle').innerText = 'กรอกข้อมูล และเพิ่ม Sub-counter ล่วงหน้าได้เลย';
                document.getElementById('form-unified').reset();
                
                document.getElementById('inp_counter_id').readOnly = false;
                document.getElementById('form-unified').action = `{{ route('counter.store') }}`;
                document.getElementById('form-method').value = 'POST';

                document.querySelector('input[name="is_active"][value="1"]').click();
                document.getElementById('delete-section').style.display = 'none';
                
                // เปิดให้แท็บ Sub-Counters ทำงานได้ตั้งแต่โหมดเพิ่มข้อมูล
                document.getElementById('tab-subcounter-nav').style.display = 'block';
                document.getElementById('sub-counter-list-container').innerHTML = ''; 
                
                document.getElementById('tab-btn-general').click();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if(tabName === 'general') {
                document.getElementById('tab-btn-general').classList.add('active');
                document.getElementById('tab-general').classList.add('active');
                if(document.getElementById('form-method').value === 'PUT') {
                    document.getElementById('tab-general-delete').classList.add('active');
                }
            } else if (tabName === 'subcounters') {
                document.getElementById('tab-subcounter-nav').classList.add('active');
                document.getElementById('tab-subcounters').classList.add('active');
            }
        }

        function updateRadioUI(radio) {
            const lblActive = document.getElementById('lbl_status_active');
            const lblClose = document.getElementById('lbl_status_close');
            
            lblActive.className = 'status-radio';
            lblClose.className = 'status-radio';
            lblActive.querySelector('span').innerText = '○';
            lblClose.querySelector('span').innerText = '○';
            lblActive.querySelector('span').style.color = 'inherit';

            let isActive = false;
            if(radio.value === '1') {
                isActive = true;
                lblActive.classList.add('active-green');
                lblActive.querySelector('span').innerText = '●';
                lblActive.querySelector('span').style.color = '#38a169';
            } else {
                lblClose.classList.add('active-red');
                lblClose.querySelector('span').innerText = '●';
                lblClose.querySelector('span').style.color = '#e53e3e';
            }

            const subPills = document.querySelectorAll('.sub-pill');
            subPills.forEach(pill => {
                if (isActive) {
                    pill.className = 'sub-pill active';
                    pill.style.backgroundColor = '';
                    pill.style.color = '';
                    pill.innerHTML = `<span style="color:#38a169">●</span> เปิดใช้งาน`;
                } else {
                    pill.className = 'sub-pill';
                    pill.style.backgroundColor = '#fff5f5';
                    pill.style.color = '#e53e3e';
                    pill.innerHTML = `<span style="color:#e53e3e">●</span> ปิดใช้งาน`;
                }
            });

            document.querySelectorAll('.sub-is-active-input').forEach(input => {
                input.value = radio.value;
            });
        }

        function addSubCounterField() {
            const container = document.getElementById('sub-counter-list-container');
            // ใช้เครื่องหมาย - ถ้าช่อง ID ว่างอยู่
            const currentCounterId = document.getElementById('inp_counter_id').value || '-'; 
            currentMaxSubId++;

            const isActiveVal = document.querySelector('input[name="is_active"]:checked').value;
            const isActive = (isActiveVal === '1');
            const pillBg = isActive ? '' : 'background-color: #fff5f5; color: #e53e3e;';
            const pillClass = isActive ? 'active' : '';
            const dotColor = isActive ? '#38a169' : '#e53e3e';
            const pillText = isActive ? 'เปิดใช้งาน' : 'ปิดใช้งาน';

            container.insertAdjacentHTML('beforeend', `
                <div class="sub-counter-item">
                    <div class="sub-id" style="font-size:16px; font-weight:700; color:#FFCC00; width:40px; text-align:center;">
                        ${currentCounterId}.${currentMaxSubId}
                    </div>
                    <div class="time-inputs" style="display:flex; gap:10px; flex:1;">
                        <div class="form-group" style="margin-bottom:0; flex:1;">
                            <label style="font-size: 11px; color: #a0aec0;">เวลาเริ่ม</label>
                            <input type="time" name="new_start_time[]" class="form-control" value="08:00" style="padding: 8px;" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0; flex:1;">
                            <label style="font-size: 11px; color: #a0aec0;">เวลาสิ้นสุด</label>
                            <input type="time" name="new_end_time[]" class="form-control" value="16:00" style="padding: 8px;" required>
                        </div>
                    </div>
                    <input type="hidden" name="new_is_active[]" class="sub-is-active-input" value="${isActiveVal}">
                    <div class="sub-pill ${pillClass}" style="white-space:nowrap; ${pillBg}">
                        <span style="color:${dotColor}">●</span> ${pillText}
                    </div>
                    <button type="button" class="icon-btn" onclick="this.closest('.sub-counter-item').remove()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    </button>
                </div>
            `);
        }

        function deleteSubCounter(counterId, subId) {
            if(confirm('ยืนยันการลบเคาน์เตอร์ย่อยนี้?')) {
                const form = document.getElementById('form-delete-sub');
                form.action = `/counter/${counterId}/sub/${subId}`;
                form.submit();
            }
        }
    </script>
</body>
</html>