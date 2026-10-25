<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งรหัสผ่านใหม่ - BUU</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ time() }}">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>

    <div class="login-container">
        <div class="logo-container">
            <!-- รูปตราวงกลมมหาวิทยาลัยบูรพา -->
            <div class="sidebar-logo"><img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo" class="logo-seal">
            </div>

            <!-- ข้อความ BUU BURAPHA UNIVERSITY -->
            <div class="logo-text-group">
                <div class="logo-buu">
                    <span class="char-b">B</span><span class="char-uu">UU</span>
                </div>
                <div class="logo-sub">BURAPHA UNIVERSITY</div>
            </div>
        </div>

        <div class="login-header">
            <h1>ตั้งรหัสผ่านใหม่</h1>
            <p>กรุณากำหนดรหัสผ่านใหม่สำหรับเข้าใช้งานระบบของคุณ</p>
        </div>

        @if(session('success'))
            <div class="alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('status'))
            <div class="alert-info">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label>อีเมล (Email)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <input type="email" name="email" value="{{ old('email', $email) }}" placeholder="example@buu.ac.th" required>
                </div>
            </div>

            <div class="form-group">
                <label>รหัสผ่านใหม่ (New Password)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" name="password" id="password" placeholder="อย่างน้อย 6 ตัวอักษร" required>
                    <svg class="icon-right" id="togglePassword1" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
            </div>

            <div class="form-group">
                <label>ยืนยันรหัสผ่านใหม่ (Confirm Password)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required>
                    <svg class="icon-right" id="togglePassword2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
            </div>

            <button type="submit" class="btn-login" style="margin-top: 10px;">บันทึกรหัสผ่านใหม่</button>
        </form>

        <div class="back-link-group">
            <a href="{{ route('login') }}" class="back-link">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                กลับไปหน้าเข้าสู่ระบบ
            </a>
        </div>
    </div>

    <script>
        function setupToggle(toggleId, inputId) {
            const toggle = document.querySelector(toggleId);
            const input = document.querySelector(inputId);
            if (!toggle || !input) return;

            toggle.addEventListener('click', function () {
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');

                if (isPassword) {
                    toggle.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
                } else {
                    toggle.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
                }
            });
        }

        setupToggle('#togglePassword1', '#password');
        setupToggle('#togglePassword2', '#password_confirmation');
    </script>

</body>

</html>
