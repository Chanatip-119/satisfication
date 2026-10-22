<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไม่มีสิทธิ์เข้าถึง</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f4f7fb; color: #1a202c; font-family: "Segoe UI", Tahoma, sans-serif; }
        .notice { width: min(100%, 520px); padding: 42px 36px; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; box-shadow: 0 18px 50px rgba(26, 32, 44, .08); text-align: center; }
        .icon { width: 68px; height: 68px; display: grid; place-items: center; margin: 0 auto 20px; border-radius: 50%; background: #fff5f5; color: #c53030; font-size: 30px; font-weight: 700; }
        h1 { margin: 0 0 10px; font-size: 24px; }
        p { margin: 0; color: #718096; line-height: 1.7; }
        .actions { display: flex; justify-content: center; gap: 12px; margin-top: 28px; flex-wrap: wrap; }
        .button { display: inline-flex; justify-content: center; align-items: center; min-height: 42px; padding: 0 18px; border: 1px solid #cbd5e0; border-radius: 9px; color: #2d3748; text-decoration: none; font-weight: 600; }
        .button.primary { border-color: #FFCC00; background: #FFCC00; color: white; }
        @media (max-width: 480px) { .notice { padding: 34px 22px; } .actions { flex-direction: column; } }
    </style>
</head>
<body>
    <main class="notice" role="alert">
        <div class="icon" aria-hidden="true">403</div>
        <h1>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h1>
        <p>บัญชีของคุณยังไม่ได้รับอนุญาตให้ทำรายการนี้<br>หากคิดว่าควรใช้งานได้ กรุณาติดต่อผู้ดูแลระบบ</p>
        <div class="actions">
            <a class="button" href="javascript:history.back()">กลับหน้าก่อน</a>
            @auth
                <a class="button primary" href="{{ route('dashboard') }}">กลับหน้าแดชบอร์ด</a>
            @else
                <a class="button primary" href="{{ route('login') }}">เข้าสู่ระบบ</a>
            @endauth
        </div>
    </main>
</body>
</html>