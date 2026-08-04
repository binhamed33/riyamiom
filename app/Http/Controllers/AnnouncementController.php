<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function publish(Request $request)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'content.required' => 'اكتب نص الرسالة أولاً',
            'content.min' => 'الرسالة قصيرة جداً',
            'content.max' => 'الرسالة أطول من المسموح (2000 حرف كحد أقصى)',
        ]);

        Announcement::create([
            'content' => $validated['content'],
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم نشر رسالة اليوم — ستظهر لكل المستخدمين مرة واحدة');
    }

    public function markSeen(Announcement $announcement)
    {
        AnnouncementRead::firstOrCreate([
            'announcement_id' => $announcement->id,
            'user_id' => auth()->id(),
        ], [
            'read_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
