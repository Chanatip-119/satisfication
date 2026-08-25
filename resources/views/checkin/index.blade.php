<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Check-in</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/checkin.css') }}">
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
            <div class="logo"></div>
            <h1 class="title">Staff Check-in</h1>
            <p class="subtitle">{{ $library_name }}</p>
        </div>

        <div class="stepper">
            <div class="step {{ $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' }}">1</div>
            <div class="step {{ $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' }}">2</div>
            <div class="step {{ $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' }}">3</div>
            <div class="step {{ $step >= 4 ? ($step > 4 ? 'completed' : 'active') : '' }}">4</div>
        </div>

        @if($step == 1)
        <form action="{{ route('checkin.step1') }}" method="POST">
            @csrf
            <h2 class="section-title">เลือก Counter ที่จะประจำ</h2>
            <div class="grid-2">
                @foreach($counters as $c)
                <label class="card-selectable" onclick="document.querySelectorAll('.card-selectable').forEach(el=>el.classList.remove('selected')); this.classList.add('selected');">
                    <input type="radio" name="counter_id" value="{{ $c->id }}" required>
                    <div class="counter-name">{{ $c->name }}</div>
                    <div class="counter-time">{{ $c->time_start }} - {{ $c->time_end }}</div>
                    @if($c->is_available)
                        <div class="status available">ว่าง</div>
                    @else
                        <div class="status occupied">มีบุคลากรอยู่แล้ว</div>
                    @endif
                </label>
                @endforeach
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
                <p class="subtitle" style="font-size: 12px;">PIN Code ของคุณที่ได้รับจาก Admin</p>
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
                <a href="{{ route('checkin.index') }}" class="num-btn btn-back" style="display:flex; justify-content:center; align-items:center; text-decoration:none; font-size:16px;">← กลับ</a>
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

        <div style="margin-bottom: 32px; background: #FFF; border: 1px solid #F0F0F0; border-radius: 12px; padding: 16px;">
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

        <button type="button" class="btn btn-primary" style="margin-bottom: 12px;" onclick="document.getElementById('reviewsModal').classList.add('active')">💬 ดูรีวิวของฉันทั้งหมด</button>
        <button type="button" class="btn btn-danger" onclick="document.getElementById('checkoutModal').classList.add('active')">➔ Check-out</button>
        @endif

        @if($step == 4)
        <div class="modal" id="checkoutModal">
            <button class="modal-close" onclick="document.getElementById('checkoutModal').classList.remove('active')">✕</button>
            
            <div class="header" style="margin-top: 24px;">
                <div class="logo"></div>
                <h1 class="title">Staff Check-in</h1>
                <p class="subtitle">{{ $library_name }}</p>
            </div>

            <div class="stepper">
                <div class="step completed"></div>
                <div class="step completed"></div>
                <div class="step completed"></div>
                <div class="step completed"></div>
            </div>

            <div style="text-align: center; margin-bottom: 24px;">
                <div class="success-icon" style="background-color: #F5F6F8; color: #555;">🕒</div>
                <h2 class="title" style="margin-bottom: 8px;">Check-out หรือยัง?</h2>
                <p class="subtitle" style="font-size: 12px;">คุณต้องการ Check-out จาก{{ $counter->name }} ใช่หรือไม่?<br>หากไม่ยืนยัน ระบบจะ Auto Check-out เมื่อหมดเวลา</p>
            </div>

            <div class="alert-box">
                <div class="alert-title">⚠️ Auto Check-out: ระบบจะทำการ Check-out อัตโนมัติเมื่อถึงเวลา {{ $counter->time_end }} น. หากคุณไม่กด Check-out ด้วยตัวเอง</div>
            </div>

            <form action="{{ route('checkin.checkout') }}" method="POST">
                @csrf
                <input type="hidden" name="checkin_id" value="{{ $current_checkin_id }}">
                <div class="grid-2">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('checkoutModal').classList.remove('active')">✕ ยังไม่ออก (Auto)</button>
                    <button type="submit" class="btn btn-primary">✓ ยืนยัน Check-out</button>
                </div>
            </form>
        </div>

        <div class="modal" id="reviewsModal">
            <button class="modal-close" onclick="document.getElementById('reviewsModal').classList.remove('active')">✕</button>
            
            <div class="header" style="margin-top: 24px; margin-bottom: 16px;">
                <div class="logo" style="width: 32px; height: 32px; margin-bottom: 8px;"></div>
                <h1 class="title" style="font-size: 16px;">Staff Check-in Kiosk</h1>
                <p class="subtitle" style="font-size: 12px;">{{ $library_name }}</p>
            </div>

            <div class="stepper" style="margin-bottom: 24px;">
                <div class="step completed" style="width: 24px; height: 24px;"></div>
                <div class="step completed" style="width: 24px; height: 24px;"></div>
                <div class="step completed" style="width: 24px; height: 24px;"></div>
                <div class="step active" style="width: 24px; height: 24px;"></div>
            </div>

            <h2 class="title" style="font-size: 18px;">รีวิวของฉัน</h2>
            <p class="subtitle" style="margin-bottom: 24px;">{{ $staff->name }}</p>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">คะแนนเฉลี่ย</div>
                    <div class="stat-value">{{ $review_stats->average }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">ประเมินทั้งหมด</div>
                    <div class="stat-value">{{ $review_stats->total }}</div>
                </div>
                <div class="stat-box highlight">
                    <div class="stat-label">เชิงลบ</div>
                    <div class="stat-value">{{ $review_stats->negative }}</div>
                </div>
            </div>

            <div class="filter-group">
                <div class="filter-tag active">ทั้งหมด ({{ $review_stats->total }})</div>
                @foreach($review_filters as $filter)
                <div class="filter-tag">{{ $filter->name }} ({{ $filter->count }})</div>
                @endforeach
                <div class="filter-tag danger">เชิงลบ ({{ $review_stats->negative }})</div>
            </div>

            <div class="review-header-flex">
                <h3 style="font-size: 14px; font-weight: 600;">{{ $counter->name }} <span style="color:#888; font-weight:400;">{{ $review_stats->current_counter_total }} รีวิว</span></h3>
                <span style="color:#00C853; font-weight:600; font-size:14px;">{{ $review_stats->current_counter_average }} ★</span>
            </div>

            <div style="height: 300px; overflow-y: auto; padding-right: 4px;">
                @foreach($reviews as $review)
                <div class="review-card {{ $review->rating <= 3 ? 'negative' : '' }}">
                    <div class="review-time">{{ $review->date }} - {{ $review->time }} น.</div>
                    <div class="review-text">{{ $review->comment ?? 'ไม่มีข้อเสนอแนะ' }}</div>
                    <div class="review-stars">
                        @for($i = 1; $i <= 5; $i++)
                            {{ $i <= $review->rating ? '★' : '☆' }}
                        @endfor
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

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
</body>
</html>