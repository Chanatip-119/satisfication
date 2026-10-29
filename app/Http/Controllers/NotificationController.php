<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * [Check-in ฟีเจอร์ใหม่ ข้อ 1] API ของเมนู "การแจ้งเตือน" ใน sidebar
 * ใช้ได้เฉพาะ Executive / Super_Admin / Admin (Employee ไม่ได้รับแจ้งเตือน)
 */
class NotificationController extends Controller
{
    /** รายการแจ้งเตือนล่าสุด 20 รายการ + จำนวนที่ยังไม่อ่าน */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->isEmployee(), 403);

        $items = $user->notifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($notification) => [
                'id'      => $notification->id,
                'kind'    => $notification->data['kind'] ?? '',
                'title'   => $notification->data['title'] ?? 'แจ้งเตือน',
                'message' => $notification->data['message'] ?? '',
                'time'    => $notification->data['time_label'] ?? '',
                'read'    => $notification->read_at !== null,
            ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items'  => $items,
        ]);
    }

    /** ทำเครื่องหมายว่าอ่านแล้ว 1 รายการ */
    public function read(Request $request, string $id)
    {
        $user = $request->user();
        abort_if($user->isEmployee(), 403);

        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /** ทำเครื่องหมายว่าอ่านแล้วทั้งหมด */
    public function readAll(Request $request)
    {
        $user = $request->user();
        abort_if($user->isEmployee(), 403);

        $user->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}