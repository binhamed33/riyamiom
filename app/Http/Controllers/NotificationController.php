<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        $unreadCount = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return redirect()->route('notifications.index')->with('success', 'Notification marked as read.');
    }

    public function markAllRead(): RedirectResponse
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    public function count(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function latest(): JsonResponse
    {
        $latest = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->latest()
            ->first();

        return response()->json([
            'has_new' => $latest && $latest->created_at->gt(now()->subSeconds(30)),
            'notification' => $latest ? [
                'id' => $latest->id,
                'title' => $latest->title,
                'message' => $latest->message,
                'created_at' => $latest->created_at->diffForHumans(),
            ] : null,
        ]);
    }
}
