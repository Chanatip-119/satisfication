<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Check-in</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    {{-- [แก้ไข ระดับ 3 ข้อ 17] เพิ่ม ?v= ให้แท็บเล็ตโหลด CSS ใหม่ทุกครั้งที่ไฟล์เปลี่ยน
         เดิม: <link rel="stylesheet" href="{{ asset('css/checkin.css') }}"> (แท็บเล็ตอาจค้าง CSS เก่าใน cache) --}}
    <link rel="stylesheet" href="{{ asset('css/checkin.css') }}?v={{ filemtime(public_path('css/checkin.css')) }}">
</head>
<body>

    @if(isset($show_warning_bar) && $show_warning_bar)
    <div class="notification-bar">
        <span class="notification-icon">🔔</span>
        แจ้งเตือน: {{ $counter->name }} จะหมดเวลาใน 15 นาที ({{ $counter->time_end }}) — Staff: {{ $staff->name }}
    </div>
    @endif

    <div class="container">
        
        <div class="header">
            <div><img src="{{ asset('images/Buu-logo11.png') }}" alt="BUU Logo" style="width: 80px; height: auto; margin: 0 auto 16px; display: block; background-color: transparent;"></div>
            <h1 class="title">Staff Check-in</h1>
            <p class="subtitle">{{ $library_name }}</p>
        </div>

        <div class="stepper">
            <div class="step {{ $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' }}">1</div>
            <div class="step {{ $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' }}">2</div>
            <div class="step {{ $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' }}">3</div>
            <div class="step {{ $step >= 4 ? ($step > 4 ? 'completed' : 'active') : '' }}">4</div>
        </div>

        @if($errors->any())
        <div class="alert-box" role="alert">
            <div class="alert-title">ไม่สามารถ Check-in ได้</div>
            <div class="alert-text">{{ $errors->first() }}</div>
        </div>
        @endif

        @if($step == 1)
        <form action="{{ route('checkin.step1') }}" method="POST">
            @csrf
            <h2 class="section-title">เลือก Counter ที่จะประจำ</h2>
            <div class="grid-2">
                <?php foreach($counters as $c): ?>
                <label class="card-selectable" onclick="document.querySelectorAll('.card-selectable').forEach(el=>el.classList.remove('selected')); this.classList.add('selected');">
                    <input type="radio" name="counter_id" value="{{ $c->id }}" required>
                    <div class="counter-name">{{ $c->name }}</div>
                    <div class="counter-time">{{ $c->time_start }} - {{ $c->time_end }}</div>
                    <?php if($c->is_available): ?>
                        <div class="status available">ว่าง</div>
                    <?php else: ?>
                        <div class="status occupied">มีคนอยู่ — เลือกเพื่อ Check-in แทน</div>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">ถัดไป ➔</button>
        </form>
        @endif

        @if($step == 2)
        <form action="{{ route('checkin.step2') }}" method="POST" id="pinForm">
            @csrf
            <input type="hidden" name="counter_id" value="{{ $selected_counter_id }}">
            <input type="hidden" name="pin" id="pinInput" value="">
            
            <div style="text-align: center; margin-bottom: 24px;">
                <h2 class="section-title" style="margin-bottom: 4px;">กรอก Unique ID 4 หลัก</h2>
                <p class="subtitle" style="font-size: 13px;">PIN Code ของคุณที่ได้รับจาก Admin</p>
            </div>

            <div class="pin-display" id="pinDisplay">
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
            </div>

            <div class="numpad">
                <button type="button" class="num-btn" onclick="addPin('1')">1</button>
                <button type="button" class="num-btn" onclick="addPin('2')">2</button>
                <button type="button" class="num-btn" onclick="addPin('3')">3</button>
                <button type="button" class="num-btn" onclick="addPin('4')">4</button>
                <button type="button" class="num-btn" onclick="addPin('5')">5</button>
                <button type="button" class="num-btn" onclick="addPin('6')">6</button>
                <button type="button" class="num-btn" onclick="addPin('7')">7</button>
                <button type="button" class="num-btn" onclick="addPin('8')">8</button>
                <button type="button" class="num-btn" onclick="addPin('9')">9</button>
                <a href="{{ route('checkin.index') }}" class="num-btn btn-back" style="text-decoration:none; font-size:16px; display:flex; justify-content:center; align-items:center;">← กลับ</a>
                <button type="button" class="num-btn" onclick="addPin('0')">0</button>
                <button type="button" class="num-btn" onclick="delPin()">⌫</button>
            </div>
        </form>
        @endif

        @if($step == 3)
        <form action="{{ route('checkin.step3') }}" method="POST">
            @csrf
            <input type="hidden" name="counter_id" value="{{ $counter->id }}">
            <input type="hidden" name="staff_id" value="{{ $staff->id }}">

            @if($counter->current_staff_name)
            <div class="alert-box">
                <div class="alert-title">⚠️ บุคลากรคนก่อนจะถูก Check-out อัตโนมัติ</div>
                <div class="alert-text">
                    {{ $counter->name }} มี <strong>{{ $counter->current_staff_name }}</strong> อยู่ก่อนแล้ว หากยืนยัน ระบบจะ kick คนเก่าออกทันทีและบันทึกเวลา check-out อัตโนมัติ
                </div>
            </div>
            @endif

            <h2 class="section-title">ยืนยันตัวตน</h2>
            
            <div class="info-card">
                <div class="info-name">{{ $staff->name }}</div>
                <div class="info-sub">รหัสพนักงาน: {{ $staff->code }}</div>
            </div>
            <div style="margin-bottom: 32px;">
                <div class="detail-row">
                    <span class="detail-label">เคาน์เตอร์</span>
                    <span class="detail-value">{{ $counter->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ช่วงเวลา</span>
                    <span class="detail-value">{{ $counter->time_start }} - {{ $counter->time_end }} น.</span>
                </div>
            </div>

            <div class="grid-2" style="margin-bottom: 0;">
                <a href="{{ route('checkin.index') }}" class="btn btn-outline">กลับ</a>
                <button type="submit" class="btn btn-primary">✓ ยืนยัน Check-in</button>
            </div>
        </form>
        @endif

        @if($step == 4)
        <div style="text-align: center;">
            <div class="success-icon">✓</div>
            <h2 class="title" style="margin-bottom: 8px;">Check-in สำเร็จ!</h2>
            <p class="subtitle" style="margin-bottom: 24px;">คุณพร้อมให้บริการแล้ว ขอบคุณที่เข้าร่วมงาน</p>
        </div>

        <div style="margin-bottom: 32px; background: #FFF; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <div class="detail-row">
                <span class="detail-label">ชื่อ</span>
                <span class="detail-value">{{ $staff->name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">เคาน์เตอร์</span>
                <span class="detail-value">{{ $counter->name }} — {{ $counter->location }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check-in เมื่อ</span>
                <span class="detail-value">{{ $checkin_time }} น.</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">หมดเวลา</span>
                <span class="detail-value">{{ $counter->time_end }} น.</span>
            </div>
        </div>

        {{-- ===== [Check-in ฟีเจอร์ใหม่ ข้อ 2] ต่อเวลา ===== --}}
        {{-- ค่าปัจจุบันไว้ให้สคริปต์เทียบว่ามีการเปลี่ยนแปลง (เช่น ถึงช่วงที่ต่อเวลาได้) แล้วโหลดหน้าใหม่ --}}
        <div id="checkin-meta" hidden data-end="{{ $counter->time_end }}" data-can-extend="{{ $extension->can_extend ? '1' : '0' }}"></div>

        @if($extension->is_extended)
        <div class="alert-box" style="border-left-color: #3182ce; background: #ebf8ff;">
            <div class="alert-title" style="color: #2b6cb0;">⏱ ต่อเวลาแล้ว ถึง {{ $extension->extended_label }} น.</div>
            <div class="alert-text" style="color: #2c5282;">ระบบจะไม่ Check-out ให้อัตโนมัติ — ก่อนออกจากเคาน์เตอร์ต้องกด Check-out เองทุกครั้ง</div>
        </div>
        @endif

        @if($extension->can_extend)
        <form action="{{ route('checkin.extend') }}" method="POST"
              onsubmit="return confirm('ยืนยันต่อเวลา? เมื่อต่อเวลาแล้วต้องกด Check-out เองเท่านั้น ระบบจะไม่ Check-out ให้อัตโนมัติ');"
              style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            @csrf
            <input type="hidden" name="checkin_id" value="{{ $current_checkin_id }}">
            <div style="font-weight: 700; color: #2d3748; margin-bottom: 4px;">ต่อเวลาปฏิบัติงาน</div>
            <div style="font-size: 13px; color: #718096; margin-bottom: 12px;">เหลือเวลาอีก {{ $extension->minutes_left }} นาที (หมดเวลา {{ $counter->time_end }} น.)</div>
            <div style="display: flex; gap: 10px;">
                <select name="hours" style="flex: 1; padding: 12px; border: 1.5px solid #cbd5e0; border-radius: 10px; font-size: 15px; font-family: inherit; background: #fff;">
                    @foreach($extension->options as $hours)
                        <option value="{{ $hours }}">ต่อ {{ $hours }} ชั่วโมง (ถึง {{ \Carbon\Carbon::createFromFormat('H:i', $counter->time_end)->addHours($hours)->format('H:i') }} น.)</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary" style="width: auto; padding: 12px 20px;">ต่อเวลา</button>
            </div>
        </form>
        @endif
        {{-- ===== [จบ ข้อ 2: ต่อเวลา] ===== --}}

        <button type="button" class="btn btn-primary" style="margin-bottom: 12px;" onclick="document.getElementById('reviewsModal').classList.add('active')">ดูรีวิวของฉันทั้งหมด</button>
        <button type="button" class="btn btn-danger" onclick="document.getElementById('checkoutModal').classList.add('active')">➔ Check-out</button>
        @endif

        <!-- ======================= MODAL SECTION ======================= -->
        
        @if($step == 4)
        <!-- Checkout Modal -->
        <div class="modal" id="checkoutModal">
            <button class="modal-close" onclick="document.getElementById('checkoutModal').classList.remove('active')">✕</button>
            
            <div class="header" style="margin-top: 10px;">
                <div><img src="{{ asset('images/Buu-logo11.png') }}" alt="BUU Logo" style="width: 80px; height: auto; margin: 0 auto 16px; display: block; background-color: transparent;"></div>
                <h1 class="title">Staff Check-in</h1>
                <p class="subtitle">{{ $library_name }}</p>
            </div>

            <div style="text-align: center; margin-bottom: 24px;">
                <div class="success-icon" style="background-color: #F5F6F8; color: #4a5568;">🕒</div>
                <h2 class="title" style="margin-bottom: 8px;">Check-out หรือยัง?</h2>
                {{-- [Check-in ฟีเจอร์ใหม่ ข้อ 2] ถ้าต่อเวลาแล้ว ไม่มี Auto Check-out --}}
                @if($extension->is_extended)
                <p class="subtitle" style="font-size: 13px; line-height: 1.5;">คุณต้องการ Check-out จาก {{ $counter->name }} ใช่หรือไม่?<br>คุณต่อเวลาไว้ ระบบจะไม่ Check-out ให้อัตโนมัติ</p>
                @else
                <p class="subtitle" style="font-size: 13px; line-height: 1.5;">คุณต้องการ Check-out จาก {{ $counter->name }} ใช่หรือไม่?<br>หากไม่ยืนยัน ระบบจะ Auto Check-out เมื่อหมดเวลา</p>
                @endif
            </div>

            @unless($extension->is_extended)
            <div class="alert-box">
                <div class="alert-title">⚠️ Auto Check-out</div>
                <div class="alert-text">ระบบจะทำการ Check-out อัตโนมัติเมื่อถึงเวลา {{ $counter->time_end }} น. หากคุณไม่กด Check-out ด้วยตัวเอง</div>
            </div>
            @endunless

            <form action="{{ route('checkin.checkout') }}" method="POST">
                @csrf
                <input type="hidden" name="checkin_id" value="{{ $current_checkin_id }}">
                <div class="grid-2" style="margin-bottom: 0;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('checkoutModal').classList.remove('active')">✕ ยังไม่ออก (Auto)</button>
                    <button type="submit" class="btn btn-primary">✓ ยืนยัน Check-out</button>
                </div>
            </form>
        </div>

        <!-- Reviews Modal -->
        <div class="modal" id="reviewsModal">
            <button class="modal-close" onclick="document.getElementById('reviewsModal').classList.remove('active')">✕</button>
            
            <div class="header" style="margin-top: 10px; margin-bottom: 16px;">
                <div><img src="{{ asset('images/Buu-logo11.png') }}" alt="BUU Logo" style="width: 50px; height: auto; margin: 0 auto 8px; display: block; background-color: transparent;"></div>
                <h1 class="title" style="font-size: 18px;">รีวิวของฉัน</h1>
                <p class="subtitle">{{ $staff->name }}</p>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">คะแนนเฉลี่ย</div>
                    <div class="stat-value" id="stat-avg-val">{{ $review_stats->average }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">ประเมินทั้งหมด</div>
                    <div class="stat-value" id="stat-total-val">{{ $review_stats->total }}</div>
                </div>
                <div class="stat-box highlight">
                    <div class="stat-label">เชิงลบ</div>
                    <div class="stat-value" id="stat-neg-val">{{ $review_stats->negative }}</div>
                </div>
            </div>

            <div class="filter-group">
                <div class="filter-tag primary active" id="btn-filter-all" onclick="filterReviews('all')">ทั้งหมด (<span id="filter-all-count">{{ $review_stats->total }}</span>)</div>
                <div class="filter-tag danger" id="btn-filter-neg" onclick="filterReviews('negative')">เชิงลบ (<span id="filter-neg-count">{{ $review_stats->negative }}</span>)</div>
            </div>

            <div class="review-header-flex">
                <h3 style="font-size: 14px; font-weight: 700; color: #2d3748;">{{ $counter->name }} <span style="color:#a0aec0; font-weight:500; font-size: 13px;" id="header-total-count">({{ $review_stats->current_counter_total }} รีวิว)</span></h3>
                <span style="color:#03543f; font-weight:700; font-size:14px;" id="header-avg-val">{{ $review_stats->current_counter_average }} ★</span>
            </div>

            <div id="review-list-container" style="height: 300px; overflow-y: auto; padding-right: 4px;">
                <?php foreach($reviews as $review): ?>
                <div class="review-card review-item <?php echo $review->rating <= 2 ? 'negative' : ''; ?>" data-rating="{{ $review->rating }}">
                    <div class="review-time">{{ $review->date }} - {{ $review->time }} น.</div>
                    <div class="review-text">{{ $review->comment ?? 'ไม่มีข้อเสนอแนะ' }}</div>
                    <div class="review-stars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <?php echo $i <= $review->rating ? '★' : '☆'; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        @endif

    </div>

    <!-- ======================= SCRIPTS ======================= -->
    
    @if($step == 2)
    <script>
        let currentPin = "";
        const maxPinLength = 4;
        
        function updateDisplay() {
            const dots = document.querySelectorAll('.pin-dot');
            dots.forEach((dot, index) => {
                if(index < currentPin.length) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            });
        }

        function addPin(num) {
            if(currentPin.length < maxPinLength) {
                currentPin += num;
                updateDisplay();
                if(currentPin.length === maxPinLength) {
                    document.getElementById('pinInput').value = currentPin;
                    document.getElementById('pinForm').submit();
                }
            }
        }

        function delPin() {
            if(currentPin.length > 0) {
                currentPin = currentPin.slice(0, -1);
                updateDisplay();
            }
        }
    </script>
    @endif

    @if($step == 4)
    <script>
    // ฟังก์ชันจัดการแท็บรีวิวและสลับสีปุ่ม
    let currentFilter = 'all';

    function filterReviews(type) {
        currentFilter = type;
        const items = document.querySelectorAll('.review-item');
        const btnAll = document.getElementById('btn-filter-all');
        const btnNeg = document.getElementById('btn-filter-neg');

        // สลับสถานะและสีปุ่ม (เพิ่ม-ลด class .active)
        if (btnAll && btnNeg) {
            if (type === 'all') {
                btnAll.classList.add('active');
                btnNeg.classList.remove('active');
            } else if (type === 'negative') {
                btnNeg.classList.add('active');
                btnAll.classList.remove('active');
            }
        }

        // คัดกรองการแสดงผลรีวิว (ดาว)
        items.forEach(item => {
            const rating = parseInt(item.getAttribute('data-rating') || '5');
            if (type === 'all') {
                item.style.display = 'block';
            } else if (type === 'negative') {
                if (rating <= 2) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            }
        });
    }

    (function () {
        // ===== [แก้ไข ระดับ 3 ข้อ 17] เปลี่ยนวิธีเช็คสถานะให้เบาลง =====
        // เดิม: ทุก 4 วินาที ดึงหน้า /checkin ทั้งหน้าซ้ำ ซึ่งทุกครั้งเซิร์ฟเวอร์ต้อง
        //       วน checkout อัตโนมัติทุกคน + สแกนกะทั้งระบบ + โหลดคะแนนทั้งหมดของพนักงาน
        //       (10 จุดเปิดพร้อมกัน ≈ 9,000 ครั้งต่อชั่วโมง)
        // ใหม่: ทุก 10 วินาที เรียก /checkin/status ที่ตอบกลับเป็น JSON เล็ก ๆ
        //       (ยังเปิดอยู่ไหม / จำนวนรีวิว / แถบเตือน 15 นาที)
        //       แล้วค่อยดึงหน้าเต็มมาอัปเดต "เฉพาะตอนที่มีอะไรเปลี่ยน" เท่านั้น
        //
        // [แก้ไข ระดับ 3 ข้อ 17] ลบโค้ดที่ override window.fetch เพื่อดัก /api/my-reviews ออก
        // เดิม: ทำให้ทุกคำขอไป /api/my-reviews ค้างตลอดไป เพื่อซ่อน error จากสคริปต์เก่า (route นี้ไม่มีในระบบแล้ว)

        const statusUrl = "{{ route('checkin.status') }}";
        const homeUrl = "{{ route('checkin.index') }}";

        function readText(id) {
            const el = document.getElementById(id);
            return el ? el.innerText.trim() : '';
        }

        // ค่าเริ่มต้นจากหน้าที่โหลดมา ใช้เทียบว่ามีอะไรเปลี่ยนหรือไม่
        let lastSignature = readText('stat-total-val') + '|' + readText('stat-neg-val') + '|'
            + (document.querySelector('.notification-bar') ? '1' : '0');

        // ดึงหน้าเต็มมาอัปเดตส่วนที่เปลี่ยน (โค้ดเดิมจาก pollLatestReviews แต่ถูกเรียกเฉพาะตอนจำเป็น)
        function refreshFromServer() {
            fetch(window.location.href, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.text();
            })
            .then(html => {
                if (!html) return;
                
                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');

                // ถ้าไม่มีเครื่องหมายถูกสีเขียว = หมดเวลาโดนเตะออกแล้ว ให้เด้งกลับหน้าแรกทันที
                if (!doc.querySelector('.success-icon')) {
                    window.location.replace(homeUrl);
                    return;
                }

                // 1. อัปเดตแถบแจ้งเตือน 15 นาที
                let newNotifBar = doc.querySelector('.notification-bar');
                let currentNotifBar = document.querySelector('.notification-bar');

                if (newNotifBar && !currentNotifBar) {
                    document.body.insertBefore(newNotifBar.cloneNode(true), document.body.firstChild);
                } else if (!newNotifBar && currentNotifBar) {
                    currentNotifBar.remove();
                } else if (newNotifBar && currentNotifBar) {
                    currentNotifBar.innerHTML = newNotifBar.innerHTML;
                }

                // 2. อัปเดตตัวเลขทุกจุดบนหน้าจอ
                // [แก้ไข ระดับ 3 ข้อ 17] แก้ id ที่พิมพ์ผิด
                // เดิม: 'filter-total-count' (ไม่มี element นี้) ทำให้ตัวเลขบนปุ่ม "ทั้งหมด (n)" ไม่อัปเดตเลย
                const idsToUpdate = [
                    'stat-avg-val', 'stat-total-val', 'stat-neg-val',
                    'filter-all-count', 'filter-neg-count',
                    'header-total-count', 'header-avg-val'
                ];
                
                idsToUpdate.forEach(id => {
                    if (doc.getElementById(id) && document.getElementById(id)) {
                        document.getElementById(id).innerText = doc.getElementById(id).innerText;
                    }
                });

                // 3. อัปเดตกล่องคอมเมนต์ด้านล่าง
                let newList = doc.getElementById('review-list-container');
                let curList = document.getElementById('review-list-container');
                if (newList && curList) {
                    curList.innerHTML = newList.innerHTML;
                    filterReviews(currentFilter); // ให้ค้างแท็บเดิมและคงสีปุ่มที่พนักงานกำลังกดดูอยู่
                }
            })
            .catch(() => {});
        }

        // เช็คสถานะแบบเบา
        function pollStatus() {
            fetch(statusUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
            .then(res => res.ok ? res.json() : null)
            .then(data => {
                if (!data) return;

                // หมดเวลากะ หรือถูกคนอื่น Check-in รับช่วงต่อ → กลับหน้าแรก
                if (!data.active) {
                    window.location.replace(homeUrl);
                    return;
                }

                // [Check-in ฟีเจอร์ใหม่ ข้อ 2] เวลาสิ้นสุดเปลี่ยน หรือถึงช่วงที่ต่อเวลาได้ → โหลดหน้าใหม่ทั้งหน้า เพื่อแสดง/ซ่อนปุ่มต่อเวลา
                const meta = document.getElementById('checkin-meta');
                if (meta && ((data.end && data.end !== meta.dataset.end)
                    || (typeof data.can_extend !== 'undefined' && (data.can_extend ? '1' : '0') !== meta.dataset.canExtend))) {
                    window.location.reload();
                    return;
                }

                const signature = data.total + '|' + data.negative + '|' + (data.warning ? '1' : '0');
                if (signature !== lastSignature) {
                    lastSignature = signature;
                    refreshFromServer();
                }
            })
            .catch(() => {});
        }

        setInterval(pollStatus, 10000);
        // ===== [จบ ข้อ 17] =====
    })();
</script>
    @endif
</body>
</html>