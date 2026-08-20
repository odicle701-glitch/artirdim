<?php


namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
     public function index()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        auth()->user()->unreadNotifications->markAsRead();

        return view('general.notifications', compact('notifications'));
    }

    public function markAsRead(string $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
