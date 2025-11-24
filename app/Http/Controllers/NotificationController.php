<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get all notifications for a user.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $notifications = Notification::where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'notifications' => $notifications
        ], 200);
    }

    /**
     * Get unread notifications for a user.
     */
    public function unread(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $notifications = Notification::where('user_id', $request->user_id)
            ->unread()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $notifications->count()
        ], 200);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $notification = Notification::findOrFail($id);

        // Verify the notification belongs to the user
        if ($notification->user_id != $request->user_id) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification
        ], 200);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $count = Notification::where('user_id', $request->user_id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'count' => $count
        ], 200);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $notification = Notification::findOrFail($id);

        // Verify the notification belongs to the user
        $userId = $request->input('user_id') ?? $request->user_id;
        if ($notification->user_id != $userId) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ], 200);
    }

    /**
     * Get notification count for a user.
     */
    public function count(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $unreadCount = Notification::where('user_id', $request->user_id)
            ->unread()
            ->count();

        $totalCount = Notification::where('user_id', $request->user_id)
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'total_count' => $totalCount
        ], 200);
    }
}

