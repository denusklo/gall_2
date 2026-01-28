<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user's notifications (paginated)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get user identifier (user_id or firebase_uid)
        $userId = $user->id;
        $firebaseUid = $user->firebase_uid ?? session()->get('verified_user_id');

        $perPage = $request->input('per_page', 15);
        $unreadOnly = $request->boolean('unread_only', false);

        $query = Notification::query()
            ->where(function ($q) use ($userId, $firebaseUid) {
                $q->where('user_id', $userId);
                if ($firebaseUid) {
                    $q->orWhere('firebase_uid', $firebaseUid);
                }
            })
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->unread();
        }

        $notifications = $query->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Get unread notification count
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $firebaseUid = $user->firebase_uid ?? session()->get('verified_user_id');

        $count = Notification::query()
            ->where(function ($q) use ($userId, $firebaseUid) {
                $q->where('user_id', $userId);
                if ($firebaseUid) {
                    $q->orWhere('firebase_uid', $firebaseUid);
                }
            })
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark notification as read
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $userId = $user->id;
        $firebaseUid = $user->firebase_uid ?? session()->get('verified_user_id');

        $notification = Notification::query()
            ->where('id', $id)
            ->where(function ($q) use ($userId, $firebaseUid) {
                $q->where('user_id', $userId);
                if ($firebaseUid) {
                    $q->orWhere('firebase_uid', $firebaseUid);
                }
            })
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'notification' => $notification
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $firebaseUid = $user->firebase_uid ?? session()->get('verified_user_id');

        $updated = Notification::query()
            ->where(function ($q) use ($userId, $firebaseUid) {
                $q->where('user_id', $userId);
                if ($firebaseUid) {
                    $q->orWhere('firebase_uid', $firebaseUid);
                }
            })
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'count' => $updated
        ]);
    }

    /**
     * Delete a notification
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $userId = $user->id;
        $firebaseUid = $user->firebase_uid ?? session()->get('verified_user_id');

        $notification = Notification::query()
            ->where('id', $id)
            ->where(function ($q) use ($userId, $firebaseUid) {
                $q->where('user_id', $userId);
                if ($firebaseUid) {
                    $q->orWhere('firebase_uid', $firebaseUid);
                }
            })
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }
}
