<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - BUU</title>
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ time() }}">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        .tabs-header {
            display: flex;
            border-bottom: 2px solid #edf2f7;
            margin-bottom: 22px;
            gap: 10px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 600;
            color: #718096;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            color: #b7791f;
            border-bottom-color: #ecc94b;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .info-card {
            background: #fffdf5;
            border: 1px solid #fef08a;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: #744210;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .form-row {
            display: flex;
            gap: 12px;
        }
        .form-row .form-group {
            flex: 1;
        }
    </style>
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
            <p>เลือกวิธีกำหนดรหัสผ่านใหม่สำหรับเข้าใช้งานระบบ</p>
        </div>

        @if(session('success'))
            <div class="alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('status'))
            <div class="alert-info">
                {{ session('status') }}
                @if(session('direct_reset_url'))
                    <div style="margin-top: 10px;">
                        <a href="{{ session('direct_reset_url') }}" style="display: inline-block; background: #3182ce; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600;">
                            คลิกที่นี่เพื่อไปหน้าตั้งรหัสผ่านใหม่
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @if($errors->any())
            <div class="alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="tabs-header">
            <button type="button" class="tab-btn {{ $errors->has('email') && !$errors->has('pin_reset_error') && !old('staff_id') ? '' : 'active' }}" onclick="switchTab('pin', this)">
                รีเซ็ตด้วยรหัส PIN
            </button>
            <button type="button" class="tab-btn {{ $errors->has('email') && !$errors->has('pin_reset_error') && !old('staff_id') ? 'active' : '' }}" onclick="switchTab('email', this)">
                ส่งลิงก์ทางอีเมล
            </button>
        </div>

        <!-- แท็บที่ 1: รีเซ็ตด้วย PIN Code ทันที ไม่ต้องรอเมล -->
        <div id="tab-pin" class="tab-content {{ $errors->has('email') && !$errors->has('pin_reset_error') && !old('staff_id') ? '' : 'active' }}">
            <div class="info-card">
                บุคลากรสามารถยืนยันตัวตนด้วย <strong>รหัสพนักงาน</strong> และ <strong>PIN Code (4 หลัก)</strong> เพื่อตั้งรหัสผ่านใหม่ได้ทันที
            </div>

            <form action="{{ route('password.reset.pin') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>อีเมล (Email)</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="example@go.buu.ac.th" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>รหัสพนักงาน</label>
                        <div class="input-group">
                            <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <input type="text" name="staff_id" value="{{ old('staff_id') }}" placeholder="เช่น 10 หรือ BUU-010" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>PIN Code (4 หลัก)</label>
                        <div class="input-group">
                            <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <input type="text" name="pin" maxlength="4" placeholder="● ● ● ●" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>รหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" name="password" minlength="6" placeholder="กำหนดรหัสผ่านใหม่" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>ยืนยันรหัสผ่านใหม่</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <input type="password" name="password_confirmation" minlength="6" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required>
                    </div>
                </div>

                <button type="submit" class="btn-login" style="margin-top: 10px;">บันทึกรหัสผ่านใหม่</button>
            </form>
        </div>

        <!-- แท็บที่ 2: ขอรับลิงก์ทางอีเมล -->
        <div id="tab-email" class="tab-content {{ $errors->has('email') && !$errors->has('pin_reset_error') && !old('staff_id') ? 'active' : '' }}">
            <p style="color: #718096; font-size: 13.5px; margin-bottom: 16px;">
                กรอกอีเมลที่ลงทะเบียนไว้ ระบบจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้ทางอีเมล
            </p>

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
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="example@go.buu.ac.th" required>
                    </div>
                </div>

                <button type="submit" class="btn-login" style="margin-top: 10px;">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
            </form>
        </div>

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
        function switchTab(tabName, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
</body>

</html>
