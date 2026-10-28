<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประเมินความพึงพอใจ</title>
    <link rel="stylesheet" href="{{ asset('css/evaluation.css') }}">
</head>
<body>
    <header class="header">
        <div class="header-logo">
            <img src="{{ asset('images/Buu-logo11.png') }}" alt="Burapha University Logo">
        </div>
        <div class="header-banner"></div>
    </header>

    <main>
        
        @if(isset($is_closed) && $is_closed)
            <div class="survey-card" style="text-align: center;">
                <div class="big-emoji">😴</div>
                <h1 style="color: #4a5568; margin-top: 15px;">เคาน์เตอร์ยังไม่เปิดให้บริการ</h1>
                <p class="subtitle">ขออภัย ณ ขณะนี้ยังไม่มีเจ้าหน้าที่ประจำ {{ $counter_name }}</p>
            </div>

        @elseif(session('success'))
            <div class="survey-card thank-you-card">
                <div class="big-emoji">😁</div>
                <h1>ขอบคุณสำหรับการประเมิน!</h1>
                <p class="thank-you-text">
                    ความคิดเห็นของคุณมีคุณค่าอย่างยิ่ง<br>
                    เราจะนำไปพัฒนาการบริการให้ดีขึ้น
                </p>
                <br>
                <a href="{{ route('evaluation.create', request('counter_sub_id')) }}" class="submit-btn" style="text-decoration: none; display: inline-block; margin-top: 20px;">กลับไปหน้าการประเมิน</a>
            </div>

            <script>
                setTimeout(function() {
                    window.location.href = "{{ route('evaluation.create', request('counter_sub_id')) }}";
                }, 3000);
            </script>

        @else
            <div class="survey-card">
                <div class="tag">{{ $counter_name }}</div>
                <h1>ประเมินความพึงพอใจ</h1>
                <p class="subtitle">คุณพึงพอใจกับการบริการมากน้อยเพียงใด?</p>

                {{-- ===== [แก้ไข ระดับ 1 ข้อ 4] แสดงข้อความ error =====
                     เดิม: หน้านี้ไม่แสดง error เลย ถ้าถูกจำกัดการส่งซ้ำหรือข้อมูลไม่ผ่าน ผู้ใช้จะไม่รู้ว่าคะแนนไม่ถูกบันทึก --}}
                @if($errors->any())
                    <div role="alert" style="background:#fff5f5; border:1px solid #feb2b2; color:#c53030; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; font-weight:600;">
                        {{ $errors->first() }}
                    </div>
                @endif
                {{-- ===== [จบ ข้อ 4] ===== --}}

                {{-- ===== [เพิ่มใหม่ ระดับ 3 ข้อ 14] ข้อความเตือนเมื่อยังไม่เลือกคะแนน ===== --}}
                <div id="rating-error" role="alert" style="display: none; background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600;">
                    กรุณาเลือกระดับความพึงพอใจก่อนส่งการประเมิน
                </div>

                {{-- [แก้ไข ระดับ 3 ข้อ 14] เพิ่ม novalidate + ตรวจด้วย JS
                     เดิม: radio คะแนนเป็น required แต่ถูกซ่อนด้วย display:none เบราว์เซอร์จึงบล็อกการส่ง
                           แต่แสดงข้อความเตือนไม่ได้ (ไม่มีช่องให้ชี้) ผู้ใช้กดส่งแล้วไม่มีอะไรเกิดขึ้น
                     ใหม่: ปิดการตรวจของเบราว์เซอร์ แล้วตรวจเองพร้อมแสดงข้อความ (Controller ยังตรวจซ้ำอีกชั้น) --}}
                <form action="{{ route('evaluation.store') }}" method="POST" id="evaluation-form" novalidate onsubmit="return validateEvaluationForm()">
                    @csrf 

                    <input type="hidden" name="checkin_id" value="{{ $checkin_id }}">
                    <input type="hidden" name="counter_sub_id" value="{{ $counter_sub_id }}">

                    <div class="rating-group">
                        <label class="rating-item" onclick="selectRating(this)">
                            <input type="radio" name="rating" value="5" required style="display: none;">
                            <span class="emoji">😁</span>
                            <span class="rating-label">ดีมาก</span>
                        </label>
                        <label class="rating-item" onclick="selectRating(this)">
                            <input type="radio" name="rating" value="4" required style="display: none;">
                            <span class="emoji">🙂</span>
                            <span class="rating-label">ดี</span>
                        </label>
                        <label class="rating-item" onclick="selectRating(this)">
                            <input type="radio" name="rating" value="3" required style="display: none;">
                            <span class="emoji">😐</span>
                            <span class="rating-label">ปานกลาง</span>
                        </label>
                        <label class="rating-item" onclick="selectRating(this)">
                            <input type="radio" name="rating" value="2" required style="display: none;">
                            <span class="emoji">😓</span>
                            <span class="rating-label">พอใช้</span>
                        </label>
                        <label class="rating-item" onclick="selectRating(this)">
                            <input type="radio" name="rating" value="1" required style="display: none;">
                            <span class="emoji">😡</span>
                            <span class="rating-label">ปรับปรุง</span>
                        </label>
                    </div>

                    <div class="feedback-group">
                        <label for="feedback">
                            ข้อเสนอแนะ
                            <span id="feedback-hint" style="display: none; color: #d97706; font-size: 13px; font-weight: 500; margin-top: 4px;">
                                (โปรดเขียนข้อเสนอแนะของคุณเพื่อให้เรานำไปปรับแก้ไขให้ดียิ่งขึ้น)
                            </span>
                        </label>
                        {{-- [แก้ไข ระดับ 2 ข้อ 11] จำกัด 255 ตัวอักษรตามคอลัมน์ใน DB + แสดงตัวนับ
                             และคืนข้อความเดิม (old) ถ้าส่งไม่ผ่าน ผู้ใช้จะได้ไม่ต้องพิมพ์ใหม่
                             เดิม: <textarea id="feedback" name="comment"></textarea> --}}
                        <textarea id="feedback" name="comment" maxlength="255" oninput="updateCommentCount(this)">{{ old('comment') }}</textarea>
                        <div id="comment-count" style="text-align: right; font-size: 12px; color: #a0aec0; margin-top: 6px;">0/255</div>
                    </div>

                    {{-- [แก้ไข ระดับ 3 ข้อ 14] เพิ่ม id เพื่อปิดปุ่มหลังกดส่ง กันกดซ้ำ --}}
                    <button type="submit" class="submit-btn" id="evaluation-submit-btn">ส่งการประเมิน</button>
                </form>
            </div>
        @endif
    </main>

    <script>
        // กำหนดให้ซ่อนข้อความแจ้งเตือนข้อเสนอแนะไว้ตอนเริ่มต้น
        document.addEventListener("DOMContentLoaded", function() {
            let hintText = document.getElementById('feedback-hint');
            if (hintText) hintText.style.display = 'none';

            // [เพิ่มใหม่ ระดับ 2 ข้อ 11] นับตัวอักษรตั้งแต่เปิดหน้า (กรณีมีข้อความเดิมคืนมาจาก old)
            let feedback = document.getElementById('feedback');
            if (feedback) updateCommentCount(feedback);
        });

        // ===== [เพิ่มใหม่ ระดับ 3 ข้อ 14] ตรวจฟอร์มก่อนส่ง =====
        function validateEvaluationForm() {
            let selected = document.querySelector('#evaluation-form input[name="rating"]:checked');
            let ratingError = document.getElementById('rating-error');

            if (!selected) {
                if (ratingError) {
                    ratingError.style.display = 'block';
                    ratingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            // กันกดส่งซ้ำระหว่างรอเซิร์ฟเวอร์ตอบกลับ
            let submitBtn = document.getElementById('evaluation-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerText = 'กำลังส่ง...';
            }
            return true;
        }
        // ===== [จบ ข้อ 14] =====

        // [เพิ่มใหม่ ระดับ 2 ข้อ 11] อัปเดตตัวนับตัวอักษร และเปลี่ยนเป็นสีส้มเมื่อใกล้ถึงขีดจำกัด
        function updateCommentCount(textarea) {
            let counter = document.getElementById('comment-count');
            if (!counter) return;
            let length = textarea.value.length;
            counter.innerText = length + '/255';
            counter.style.color = length >= 230 ? '#d97706' : '#a0aec0';
        }

        function selectRating(selectedBox) {
            let allBoxes = document.querySelectorAll('.rating-item');
            allBoxes.forEach(box => {
                box.style.backgroundColor = 'transparent';
                box.style.borderColor = '#718096';
            });
            selectedBox.style.backgroundColor = '#fff9db';
            selectedBox.style.borderColor = '#ffcc00'; 

            // [เพิ่มใหม่ ระดับ 3 ข้อ 14] เลือกคะแนนแล้ว ซ่อนข้อความเตือน
            let ratingError = document.getElementById('rating-error');
            if (ratingError) ratingError.style.display = 'none';
            
            let radioBtn = selectedBox.querySelector('input[type="radio"]');
            let ratingValue = parseInt(radioBtn.value); 
            let hintText = document.getElementById('feedback-hint');

            // แสดงคำเตือนถ้ากดคะแนน 3 หรือต่ำกว่า
            if (ratingValue <= 3) {
                hintText.style.display = 'block'; 
            } else {
                hintText.style.display = 'none'; 
            }
        }
    </script>
</body>
</html>