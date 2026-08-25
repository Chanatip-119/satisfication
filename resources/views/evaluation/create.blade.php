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
        
        @if(session('success'))
            
            <div class="survey-card thank-you-card">
                <div class="big-emoji">😁</div>
                <h1>ขอบคุณสำหรับการประเมิน!</h1>
                <p class="thank-you-text">
                    ความคิดเห็นของคุณมีคุณค่าอย่างยิ่ง<br>
                    เราจะนำไปพัฒนาการบริการให้ดีขึ้น
                </p>
                <br>
                <a href="{{ route('evaluation.create') }}" class="submit-btn" style="text-decoration: none; display: inline-block; margin-top: 20px;">กลับไปหน้าการประเมิน</a>
            </div>

            <script>
                setTimeout(function() {
                    window.location.href = "{{ route('evaluation.create') }}";
                }, 3000);
            </script>

        @else

            <div class="survey-card">
                <div class="tag">สำนักหอสมุด</div>
                <h1>ประเมินความพึงพอใจ</h1>
                <p class="subtitle">คุณพึงพอใจกับการบริการมากน้อยเพียงใด?</p>

                <form action="{{ route('evaluation.store') }}" method="POST">
                    
                    @csrf 

                    <input type="hidden" name="checkin_id" value="1">

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
                            <span id="feedback-hint" style="display: none; color: #e6b800; font-size: 14px; font-weight: 500; margin-left: 5px;">
                                (โปรดเขียนข้อเสนอแนะของคุณเพื่อให้เรานำไปปรับแก้ไขให้ดียิ่งขึ้น)
                            </span>
                        </label>
                        <textarea id="feedback" name="comment"></textarea>
                    </div>

                    <button type="submit" class="submit-btn" style="width: 100%;">ส่งการประเมิน</button>
                </form>
            </div>

        @endif

    </main>

    <script>
        function selectRating(selectedBox) {
            let allBoxes = document.querySelectorAll('.rating-item');
            allBoxes.forEach(box => {
                box.style.backgroundColor = 'transparent';
                box.style.borderColor = '#718096';
            });
            selectedBox.style.backgroundColor = '#f7fafc';
            selectedBox.style.borderColor = '#ffcc00'; 
            let radioBtn = selectedBox.querySelector('input[type="radio"]');
            let ratingValue = parseInt(radioBtn.value); 
            let hintText = document.getElementById('feedback-hint');

            if (ratingValue <= 3) {
                hintText.style.display = 'inline'; 
            } else {
                hintText.style.display = 'none'; 
            }
        }
    </script>
</body>
</html>