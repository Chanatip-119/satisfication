<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - BUU</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ time() }}">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <div class="login-container">
        <div class="logo-container">
            <!-- รูปตราวงกลมมหาวิทยาลัยบูรพา -->
            <div class="sidebar-logo"><img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo" class="logo-seal"></div>

            <!-- แบบที่ 1: ใช้ HTML/CSS สร้างตัวอักษร BUU ให้สีตรงกับภาพต้นแบบเป๊ะ (B เหลือง, UU เทา) -->
            <div class="logo-text-group">
                <div class="logo-buu">
                    <span class="char-b">B</span><span class="char-uu">UU</span>
                </div>
                <div class="logo-sub">BURAPHA UNIVERSITY</div>
            </div>
        </div>

        <div class="login-header">
            <h1>เข้าสู่ระบบ</h1>
            <p>กรุณากรอกชื่อผู้ใช้งานและรหัสผ่านเพื่อเข้าสู่ระบบ</p>
        </div>

        <form action="{{ url('/checkin') }}" method="GET">
            @csrf
            <div class="form-group">
                <label>ชื่อผู้ใช้งาน (Username)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <input type="text" name="username" placeholder="กรอกชื่อผู้ใช้งาน" required>
                </div>
            </div>

            <div class="form-group">
                <label>รหัสผ่าน (Password)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" name="password" id="password" placeholder="••••••" required>
                    <svg class="icon-right" id="togglePassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
            </div>

            <div class="form-options">
                <label class="checkbox-group">
                    <input type="checkbox" name="remember" checked>
                    <div class="checkmark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <span>จดจำการเข้าสู่ระบบ</span>
                </label>
                <a href="#" class="forgot-password">ลืมรหัสผ่าน?</a>
            </div>

            <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            if (type === 'password') {
                this.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            } else {
                this.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            }
        });
    </script>
</body>
</html>
