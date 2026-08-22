<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Suggestion;
use App\Services\DiscordNotifier;
use App\Services\PanelReporter;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    /** الصندوق لفريق المكتب — العميل لا يصله حتى بكتابة الرابط مباشرة. */
    private function denyClients(): void
    {
        abort_if(auth()->user()?->isClient(), 403, 'غير مصرح لك بالوصول');
    }

    public function index()
    {
        $this->denyClients();

        // كل مستخدم يرى اقتراحاته هو فقط
        $suggestions = Suggestion::where('user_id', auth()->id())->latest()->limit(50)->get();

        Suggestion::where('user_id', auth()->id())
            ->whereNotNull('developer_reply')
            ->where('reply_read', false)
            ->update(['reply_read' => true]);

        return view('suggestions.index', compact('suggestions'));
    }

    public function store(Request $request)
    {
        $this->denyClients();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'content.required' => 'اكتب اقتراحك أولاً',
            'content.min' => 'يجب وصف الاقتراح بوصف جيد — 20 حرفاً على الأقل',
            'content.max' => 'الاقتراح أطول من المسموح (2000 حرف كحد أقصى)',
            'title.max' => 'العنوان أطول من المسموح (160 حرفاً)',
        ]);

        $suggestion = Suggestion::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            // لقطة السياق تُحفظ الآن، فلا تتغيّر لو تبدّل دور الموظف لاحقاً
            'context' => \App\Support\SuggestionContext::capture($request, auth()->user()),
        ]);

        $sent = DiscordNotifier::sendSuggestion(auth()->user(), $suggestion);

        // وإلى لوحة مُداوَلة إن كان هذا المكتب مربوطاً بها — خامد وإلا
        $reached = PanelReporter::sendSuggestion($suggestion);

        return back()->with('success', ($sent || $reached)
            ? 'تم إرسال اقتراحك بنجاح — شكراً لمساهمتك'
            : 'تم حفظ اقتراحك وسنراجعه — تعذّر إبلاغ فريق التطوير فوراً');
    }

    public function reply(Request $request, Suggestion $suggestion)
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'reply.required' => 'اكتب الرد أولاً',
            'reply.min' => 'الرد قصير جداً',
            'reply.max' => 'الرد أطول من المسموح (2000 حرف كحد أقصى)',
        ]);

        $suggestion->update([
            'developer_reply' => $validated['reply'],
            'replied_at' => now(),
            'reply_read' => false,
        ]);

        Notification::create([
            'user_id' => $suggestion->user_id,
            'title' => 'ردّ المطوّر على اقتراحك',
            'message' => mb_substr($validated['reply'], 0, 100),
            'type' => Notification::TYPE_INFO,
            'notifiable_type' => Suggestion::class,
            'notifiable_id' => $suggestion->id,
            'is_read' => false,
            'message_count' => 1,
        ]);

        return back()->with('success', 'تم إرسال الرد وإشعار صاحب الاقتراح');
    }

    public function setStatus(Request $request, Suggestion $suggestion)
    {
        $request->validate([
            'status' => ['required', 'in:pending,implemented'],
        ], [
            'status.required' => 'حدد الحالة المطلوبة',
            'status.in' => 'حالة غير صالحة',
        ]);

        $suggestion->update(['status' => $request->status]);

        if ($request->status === Suggestion::STATUS_IMPLEMENTED) {
            Notification::create([
                'user_id' => $suggestion->user_id,
                'title' => 'تم تنفيذ اقتراحك',
                'message' => 'اقتراحك «' . mb_substr($suggestion->content, 0, 60) . '» تم تنفيذه — شكراً لمشاركتك',
                'type' => Notification::TYPE_SUCCESS,
                'notifiable_type' => Suggestion::class,
                'notifiable_id' => $suggestion->id,
                'is_read' => false,
                'message_count' => 1,
            ]);
        }

        return back()->with('success', $request->status === Suggestion::STATUS_IMPLEMENTED
            ? 'تم تحديد الاقتراح كمنفَّذ وإشعار صاحبه'
            : 'تم تحديد الاقتراح كقيد الدراسة أو التنفيذ');
    }

    public function update(Request $request, Suggestion $suggestion)
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'content.required' => 'اكتب نص الاقتراح أولاً',
            'content.min' => 'يجب وصف الاقتراح بوصف جيد — 20 حرفاً على الأقل',
            'content.max' => 'الاقتراح أطول من المسموح (2000 حرف كحد أقصى)',
        ]);

        $suggestion->update(['content' => $validated['content']]);

        return back()->with('success', 'تم تعديل الاقتراح');
    }

    public function destroy(Suggestion $suggestion)
    {
        Notification::where('notifiable_type', Suggestion::class)
            ->where('notifiable_id', $suggestion->id)
            ->delete();

        $suggestion->delete();

        return back()->with('success', 'تم حذف الاقتراح نهائياً');
    }
}
