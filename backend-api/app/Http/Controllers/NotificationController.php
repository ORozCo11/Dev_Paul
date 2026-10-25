<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * GET /notifications — Fetch notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json($notifications);
    }

    /**
     * PUT /notifications/{notification}/read — Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json($notification);
    }

    /**
     * PUT /notifications/read-all — Mark all notifications for the user as read.
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * DELETE /notifications/{notification} — Delete a specific notification.
     */
    public function destroy(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted successfully.']);
    }
}
