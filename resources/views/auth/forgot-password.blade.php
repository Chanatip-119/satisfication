<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - BUU</title>
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
            <h1>ลืมรหัสผ่าน</h1>
            <p>กรุณากรอกอีเมลของคุณที่ลงทะเบียนไว้ ระบบจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้ทางอีเมล</p>
        </div>

        @if(session('success'))
            <div class="alert-success">
                ✅ {{ session('success') }}
            </div>
        @endif

        @if(session('status'))
            <div class="alert-info">
                ℹ️ {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert-danger">
                @foreach ($errors->all() as $error)
                    <div>⚠️ {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>อีเมล (Email)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="example@buu.ac.th" required autofocus>
                </div>
            </div>

            <button type="submit" class="btn-login" style="margin-top: 10px;">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
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

</body>

</html>
