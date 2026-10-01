<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code เคาน์เตอร์</title>
    <!-- โหลด CSS พร้อมกัน Cache -->
    <link rel="stylesheet" href="{{ asset('css/schedule.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #eef2f6; font-family: 'Sarabun', sans-serif; }
        
        .qr-header { margin-bottom: 30px; }
        .qr-header h1 { font-size: 24px; font-weight: 700; color: #1a202c; margin-bottom: 5px; }
        .qr-header p { color: #a0aec0; font-size: 14px; margin: 0; }
        
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .qr-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .qr-image-container {
            width: 220px;
            height: 220px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
        }
        
        .qr-image-container img {
            width: 180px;
            height: 180px;
            border-radius: 8px;
        }
        
        .qr-title { font-weight: 700; font-size: 16px; color: #1a202c; margin-bottom: 5px; }
        .qr-subtitle { font-size: 13px; color: #a0aec0; margin-bottom: 20px; }
        
        .btn-download {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-download:hover { background: #1d4ed8; }
        
        .qr-card.disabled .qr-image-container {
            background: #f1f5f9;
        }
        
        .qr-card.disabled .qr-title, .qr-card.disabled .qr-subtitle {
            color: #a0aec0;
        }
        
        .btn-disabled {
            width: 100%;
            background: #f8fafc;
            color: #94a3b8;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: not-allowed;
        }
        
        .qr-placeholder {
            width: 180px;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e0;
            font-size: 40px;
            background: #e2e8f0;
            border-radius: 8px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    
    <div class="sidebar">
        <div class="sidebar-logo"><img src="{{ asset('images/Buu-logo11.png') }}" alt="Logo"></div>
        <div class="menu-category">หลัก</div>
       <a href="{{ url('/dashboard') }}" class="menu-item {{ request()->is('/dashboard') ? 'active' : '' }}">แดชบอร์ด</a>
        <a href="{{ route('schedule.index') }}" class="menu-item {{ Route::is('schedule.*') ? 'active' : '' }}">ตารางปฏิบัติงาน</a>
        <a href="{{ route('report.counter') }}" class="menu-item {{ Route::is('report.counter') ? 'active' : '' }}">รายงานเคาน์เตอร์</a>
        <a href="{{ route('report.staff') }}" class="menu-item {{ Route::is('report.staff') ? 'active' : '' }}">รายงาน Staff</a>
        <div class="menu-category">จัดการ</div>
        <a href="{{ route('counter.index') }}" class="menu-item">เคาน์เตอร์</a>
        <a href="{{ route('staff.index') }}" class="menu-item">บุคลากร</a>
        <a href="{{ url('/qrcode') }}" class="menu-item active">QR Code</a>
        <div class="menu-category">ระบบ</div>
        <a href="{{ route('report.export.index') }}" class="menu-item">ส่งออกรายงาน</a>
        <a href="{{ url('/') }}" class="logout-btn"></i>↩ Logout</a>
    </div>

    <div class="content">
        <div class="qr-header">
            <h1>QR Code เคาน์เตอร์</h1>
            <p>สร้างและดาวน์โหลด QR Code สำหรับแต่ละเคาน์เตอร์</p>
        </div>

        <div class="qr-grid">
            @foreach($counterSubs as $sub)
                @php
                    $isActive = $sub->is_active;
                    $locationText = $sub->counter ? $sub->counter->counter_location : '';
                    $timeText = "";
                    if(isset($schedules[$sub->counter_sub_id]) && $schedules[$sub->counter_sub_id]->isNotEmpty()) {
                        $sch = $schedules[$sub->counter_sub_id]->first();
                        $timeText = substr($sch->start_time, 0, 5) . '-' . substr($sch->end_time, 0, 5);
                    }
                    $displaySubtitle = $timeText ? $locationText . " — " . $timeText : $locationText;
                    
                    // Route for evaluation
                    $evaluationUrl = route('evaluation.create', ['counter_sub_id' => $sub->counter_sub_id]);
                    // Using goqr.me or qrserver API
                    $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($evaluationUrl) . "&margin=15";
                @endphp

                @if($isActive)
                    <div class="qr-card">
                        <div class="qr-image-container" id="qr-container-{{ $sub->counter_sub_id }}">
                            <img src="{{ $qrImageUrl }}" alt="QR Code {{ $sub->counter_sub_id }}" crossorigin="anonymous" id="qr-img-{{ str_replace('.', '_', $sub->counter_sub_id) }}">
                        </div>
                        <div class="qr-title">เคาน์เตอร์ {{ $sub->counter_sub_id }}</div>
                        <div class="qr-subtitle">{{ $displaySubtitle }}</div>
                        <button onclick="downloadQR('qr-img-{{ str_replace('.', '_', $sub->counter_sub_id) }}', 'เคาน์เตอร์_{{ $sub->counter_sub_id }}')" class="btn-download">
                            <i class="fas fa-download"></i> ดาวน์โหลด PNG
                        </button>
                    </div>
                @else
                    <div class="qr-card disabled">
                        <div class="qr-image-container">
                            <div class="qr-placeholder">
                                <i class="fas fa-qrcode"></i>
                            </div>
                        </div>
                        <div class="qr-title">เคาน์เตอร์ {{ $sub->counter_sub_id }}</div>
                        <div class="qr-subtitle">ปิดให้บริการ</div>
                        <div class="btn-disabled">
                            <i class="fas fa-lock"></i> ปิดใช้งาน
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <script>
        function downloadQR(imgId, filename) {
            const img = document.getElementById(imgId);
            if(!img) return;
            
            fetch(img.src)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename + '.png';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(() => {
                    const a = document.createElement('a');
                    a.href = img.src;
                    a.download = filename + '.png';
                    a.target = '_blank';
                    a.click();
                });
        }
    </script>
</body>
</html>
