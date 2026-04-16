<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\User\Notifications\FriendRequestNoti;
use App\Modules\User\Notifications\SystemAlertNoti;
use App\Modules\Chat\Notifications\MessageReactionNoti;
use App\Modules\Chat\Notifications\GroupInviteNoti; 

class NotificationController extends Controller
{
    /**
     * Return the current user's notifications (paginated, latest first).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Return latest notifications first, paginate by 15
        $notifications = $user->notifications()->paginate(15);
        
        return response()->json([
            'status' => 'success',
            'data' => $notifications
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        
        if ($notification) {
            $notification->markAsRead();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read'
            ]);
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Notification not found'
        ], 404);
    }

    /**
     * Mark all notifications for the user as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();
        
        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Return the count of unread notifications.
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }
}
