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

        // الحدّ بعد التحقّق لا قبله: كتابة نصّ قصير خطأٌ يُصحَّح لا
        // محاولةُ إغراق. كان الحدّ في المسار فيُستهلك على كل خطأ، فمن
        // أخطأ خمس مرّات يُحبس عشر دقائق ويظنّ النظام معطّلاً.
        $limitKey = 'suggestion:' . auth()->id();

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limitKey, 10)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($limitKey);

            return back()->withInput()->with('error', __('app.suggestion_rate_limited', [
                'minutes' => max(1, (int) ceil($seconds / 60)),
            ]));
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'content.required' => 'اكتب اقتراحك أولاً',
            'content.min' => 'يجب وصف الاقتراح بوصف جيد — 20 حرفاً على الأقل',
            'content.max' => 'الاقتراح أطول من المسموح (2000 حرف كحد أقصى)',
            'title.max' => 'العنوان أطول من المسموح (160 حرفاً)',
        ]);

        \Illuminate\Support\Facades\RateLimiter::hit($limitKey, 600);

        $suggestion = Suggestion::create([
            'user_id' => auth()->id(),
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            // لقطة السياق تُحفظ الآن، فلا تتغيّر لو تبدّل دور الموظف لاحقاً
            'context' => \App\Support\SuggestionContext::capture($request, auth()->user()),
        ]);

        // الاقتراح حُفظ. وما بعده إبلاغ لا يجوز أن يُفشل عمل الموظف:
        // لو تعذّر ديسكورد أو تعذّرت اللوحة، اقتراحه محفوظ عنده ويُسلَّم
        // لاحقاً. ولهذا كل ما يلي مغلَّف، والتسليم في الطابور.
        try {
            DiscordNotifier::sendSuggestion(auth()->user(), $suggestion);
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            \App\Jobs\DeliverSuggestionJob::dispatch($suggestion->id);
        } catch (\Throwable $e) {
            // تعذّر وضعه في الطابور: يبقى «معلّقاً» ويلتقطه الأمر الدوري
            report($e);
        }

        return back()->with('success', 'تم إرسال اقتراحك بنجاح — شكراً لمساهمتك');
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

        \App\Support\Notify::send(
            userId: $suggestion->user_id,
            titleKey: 'app.notif_suggestion_reply_title',
            messageKey: 'app.notif_passthrough',
            params: ['text' => mb_substr($validated['reply'], 0, 100)],
            type: Notification::TYPE_INFO,
            notifiableType: Suggestion::class,
            notifiableId: $suggestion->id,
        );

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
            \App\Support\Notify::send(
                userId: $suggestion->user_id,
                titleKey: 'app.notif_suggestion_done_title',
                messageKey: 'app.notif_suggestion_done_body',
                params: ['excerpt' => mb_substr($suggestion->content, 0, 60)],
                type: Notification::TYPE_SUCCESS,
                notifiableType: Suggestion::class,
                notifiableId: $suggestion->id,
            );
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
