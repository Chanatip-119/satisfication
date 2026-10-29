{{--
    [Check-in ฟีเจอร์ใหม่ ข้อ 1] เมนู "การแจ้งเตือน" ใน sidebar
    - แสดงเฉพาะ Executive / Super_Admin / Admin
    - ตัวเลขสีแดง = จำนวนที่ยังไม่อ่าน (อัปเดตเองทุก 30 วินาที)
    - กดแล้วเปิดแผงรายการแจ้งเตือนด้านข้าง sidebar
    ใช้: @include('partials.notification-bell') ใต้เมนูแดชบอร์ดของทุกหน้า
--}}
@if(auth()->check() && !auth()->user()->isEmployee())
<a href="#" class="menu-item notif-menu-item" onclick="toggleNotifPanel(event)">
    การแจ้งเตือน
    <span id="notif-badge" class="notif-badge" style="display: none;">0</span>
</a>

<div id="notif-panel" class="notif-panel" role="dialog" aria-label="การแจ้งเตือน">
    <div class="notif-panel-header">
        <strong>การแจ้งเตือน</strong>
        <button type="button" class="notif-read-all" onclick="markAllNotifRead()">อ่านทั้งหมด</button>
    </div>
    <div id="notif-list" class="notif-list">
        <div class="notif-empty">กำลังโหลด...</div>
    </div>
</div>

<style>
    .notif-menu-item { display: flex !important; align-items: center; justify-content: space-between; }
    .notif-badge { background: #e53e3e; color: #fff; font-size: 12px; font-weight: 700; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; }
    .notif-panel { display: none; position: fixed; left: 250px; top: 20px; width: 380px; max-width: calc(100vw - 270px); max-height: 75vh; background: #fff; border-radius: 12px; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18); z-index: 2000; overflow: hidden; flex-direction: column; }
    .notif-panel.open { display: flex; }
    .notif-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid #edf2f7; font-size: 15px; color: #1a202c; }
    .notif-read-all { background: none; border: none; color: #b7791f; font-weight: 600; font-size: 13px; cursor: pointer; font-family: inherit; }
    .notif-list { overflow-y: auto; }
    .notif-item { padding: 12px 18px; border-bottom: 1px solid #f7fafc; cursor: pointer; }
    .notif-item:hover { background: #f8fafc; }
    .notif-item.unread { background: #fffbeb; }
    .notif-item.unread:hover { background: #fef3c7; }
    .notif-item-title { font-size: 14px; font-weight: 700; color: #c53030; margin-bottom: 4px; }
    .notif-item-message { font-size: 13px; color: #4a5568; line-height: 1.5; }
    .notif-item-time { font-size: 11px; color: #a0aec0; margin-top: 4px; }
    .notif-empty { padding: 30px 18px; text-align: center; color: #a0aec0; font-size: 13px; }
</style>

<script>
    (function () {
        const listUrl = "{{ route('notifications.index') }}";
        const readAllUrl = "{{ route('notifications.readAll') }}";
        const readUrlBase = "{{ url('/notifications') }}/";
        const csrfToken = "{{ csrf_token() }}";

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
        }

        function render(data) {
            const badge = document.getElementById('notif-badge');
            const list = document.getElementById('notif-list');
            if (!badge || !list) return;

            badge.innerText = data.unread > 99 ? '99+' : data.unread;
            badge.style.display = data.unread > 0 ? 'inline-flex' : 'none';

            if (!data.items || data.items.length === 0) {
                list.innerHTML = '<div class="notif-empty">ยังไม่มีการแจ้งเตือน</div>';
                return;
            }

            list.innerHTML = data.items.map(item => `
                <div class="notif-item ${item.read ? '' : 'unread'}" data-id="${esc(item.id)}" onclick="readNotif(this)">
                    <div class="notif-item-title">${esc(item.title)}</div>
                    <div class="notif-item-message">${esc(item.message)}</div>
                    <div class="notif-item-time">${esc(item.time)}</div>
                </div>`).join('');
        }

        function load() {
            fetch(listUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
                .then(res => res.ok ? res.json() : null)
                .then(data => { if (data) render(data); })
                .catch(() => {});
        }

        function post(url) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            });
        }

        window.toggleNotifPanel = function (event) {
            event.preventDefault();
            event.stopPropagation();
            const panel = document.getElementById('notif-panel');
            panel.classList.toggle('open');
            if (panel.classList.contains('open')) load();
        };

        window.readNotif = function (el) {
            if (!el.classList.contains('unread')) return;
            post(readUrlBase + encodeURIComponent(el.dataset.id) + '/read').then(load);
        };

        window.markAllNotifRead = function () {
            post(readAllUrl).then(load);
        };

        // คลิกนอกแผงเพื่อปิด
        document.addEventListener('click', function (event) {
            const panel = document.getElementById('notif-panel');
            if (panel && panel.classList.contains('open') && !panel.contains(event.target)) {
                panel.classList.remove('open');
            }
        });

        load();
        setInterval(load, 30000);
    })();
</script>
@endif