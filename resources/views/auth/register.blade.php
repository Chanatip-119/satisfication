<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - BUU</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Sarabun', sans-serif;
        }

        body {
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 0;
        }

        .login-container {
            width: 100%;
            max-width: 550px; /* ขยายให้กว้างกว่าหน้า login นิดหน่อยเพราะฟอร์มเยอะขึ้น */
            padding: 40px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }

        .logo-container .logo-seal {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .logo-text-group {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-buu {
            font-size: 40px;
            font-weight: 800;
            line-height: 0.9;
            letter-spacing: 0.5px;
        }

        .logo-buu .char-b { color: #ffcc00; }
        .logo-buu .char-uu { color: #64748b; }

        .logo-sub {
            font-size: 9.5px;
            font-weight: 800;
            color: #1a202c;
            letter-spacing: 0.4px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .login-header {
            text-align: left;
            margin-bottom: 32px;
        }

        .login-header h1 {
            color: #1a202c;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #718096;
            font-size: 15px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #2d3748;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group .icon-left {
            position: absolute;
            left: 15px;
            color: #a0aec0;
            width: 20px;
            height: 20px;
        }

        .input-group .icon-right {
            position: absolute;
            right: 15px;
            color: #a0aec0;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            color: #2d3748;
            outline: none;
            transition: all 0.2s;
            background-color: #f8fafc;
        }

        .input-group input:focus {
            border-color: #ffcc00;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.1);
        }

        .input-group input::placeholder {
            color: #a0aec0;
        }

        .form-options {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
        }

        .checkbox-group input {
            display: none;
        }

        .checkmark {
            width: 18px;
            height: 18px;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            margin-right: 10px;
            margin-top: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .checkbox-group input:checked + .checkmark {
            background-color: #ffcc00;
            border-color: #ffcc00;
        }

        .checkmark svg {
            display: none;
            color: #ffffff;
            width: 12px;
            height: 12px;
        }

        .checkbox-group input:checked + .checkmark svg {
            display: block;
        }

        .checkbox-group span {
            color: #4a5568;
            line-height: 1.5;
        }

        .checkbox-group span a {
            color: #ecc94b;
            text-decoration: none;
            font-weight: 600;
        }
        
        .checkbox-group span a:hover {
            color: #d69e2e;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: #ffcc00;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            background-color: #ecc94b;
        }

        .register-footer {
            text-align: center;
            font-size: 14px;
            color: #718096;
        }

        .register-footer a {
            color: #ecc94b;
            text-decoration: none;
            font-weight: 700;
            margin-left: 5px;
        }

        .register-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="logo-container">
            <div class="sidebar-logo"><img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo" class="logo-seal"></div>
            <div class="logo-text-group">
                <div class="logo-buu">
                    <span class="char-b">B</span><span class="char-uu">UU</span>
                </div>
                <div class="logo-sub">BURAPHA UNIVERSITY</div>
            </div>
        </div>

        <div class="login-header">
            <h1>สมัครสมาชิกใหม่</h1>
            <p>กรอกข้อมูลด้านล่างเพื่อสร้างบัญชีผู้ใช้งานระบบ</p>
        </div>

        <form action="#" method="POST">
            @csrf
            
            <div class="form-group">
                <label>ชื่อ-นามสกุล (Full Name)</label>
                <div class="input-group">
                    <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <input type="text" name="name" placeholder="นาย/นางสาว..." required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>อีเมล (Email)</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" name="email" placeholder="email@go.buu.ac.th" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>รหัสพนักงาน (ID)</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <input type="text" name="staff_id" placeholder="เช่น BUU-010" required>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>รหัสผ่าน (Password)</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <svg class="icon-right" id="togglePassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>
                </div>

                <div class="form-group">
                    <label>ยืนยันรหัสผ่าน (Confirm)</label>
                    <div class="input-group">
                        <svg class="icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="••••••••" required>
                    </div>
                </div>
            </div>

            <div class="form-options">
                <label class="checkbox-group">
                    <input type="checkbox" name="terms" required>
                    <div class="checkmark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <span>ฉันยอมรับ <a href="#">ข้อตกลงในการใช้งาน</a> และ <a href="#">นโยบายความเป็นส่วนตัว</a> ของระบบ</span>
                </label>
            </div>

            <button type="submit" class="btn-login">ลงทะเบียน</button>

            <div class="register-footer">
                มีบัญชีผู้ใช้งานอยู่แล้ว? <a href="{{ route('login') }}">เข้าสู่ระบบที่นี่</a>
            </div>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const passwordConf = document.querySelector('#password_confirmation');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // ให้สลับของช่องยืนยันรหัสผ่านด้วย
            passwordConf.setAttribute('type', type);
            
            if (type === 'password') {
                this.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            } else {
                this.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            }
        });
    </script>
</body>
</html>
