<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    /**
     * Display a listing of user notifications.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = UserNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->paginate(15);
        $unreadCount = UserNotification::where('user_id', $user->id)->unread()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = UserNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->markAsRead();

        $unreadCount = UserNotification::where('user_id', $request->user()->id)->unread()->count();

        return response()->json([
            'message' => 'Đã đánh dấu thông báo là đã đọc.',
            'notification' => $notification,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Mark all user notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        UserNotification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Đã đánh dấu toàn bộ thông báo là đã đọc.',
            'unread_count' => 0
        ]);
    }
}
