<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessage;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\InboxService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صندوقُ واتساب — قراءةُ المحادثات والردّ عليها.
 *
 * ═══ العزل بين المكاتب ═══
 *
 * كلُّ مكتب قاعدةُ بياناتٍ مستقلّة، فلا يوجد صفٌّ لمكتبٍ آخر في هذه
 * الجداول أصلاً. ولذلك فإنّ Route Model Binding هنا آمنٌ بحكم البنية:
 * ‏`WhatsAppConversation $conversation` لا يمكن أن يُحمَّل صفَّ مكتبٍ
 * آخر لأنّ الاتصال لا يبلغ قاعدته.
 *
 * أمّا ما يُحرَس هنا فهو العزلُ داخل المكتب: من يرى ماذا، ومن يردّ،
 * ومن يربط محادثةً بقضيّة. والموكّل — دورُ client — لا يصل إلى أيٍّ
 * من هذه المسارات: مجموعةُ المسارات كلُّها خلف role:developer,admin
 * وصلاحياتٍ صريحة، وبوّابةُ العميل منفصلةٌ تماماً.
 */
class WhatsAppInboxController extends Controller
{
    public function __construct(private readonly InboxService $inbox)
    {
    }

    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');
        $search = trim((string) $request->query('q', ''));

        $query = WhatsAppConversation::query()
            ->with(['contact.client', 'assignee', 'case']);

        // الترشيح بالبحث: الاسمُ والرقمُ ونصُّ الرسائل. الرقمُ يُطبَّع
        // قبل المقارنة — من يكتب «+968 9123 4567» يقصد «96891234567».
        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search) ?? '';

            // اسمُ الموكّل يُطابَق في قاعدة البيانات كأيّ عمودٍ آخر.
            //
            // ═══ تصحيحُ مقدّمةٍ خاطئة ═══
            //
            // كان هنا تحميلُ جدول الموكّلين كلِّه إلى الذاكرة والترشيحُ
            // في PHP، بحجّة أنّ الاسم مشفَّر فلا يُطابقه LIKE. والاسمُ
            // ليس مشفَّراً: Client::$encryptable هي phone وemail
            // وaddress وnational_id وcompany_name — لا name. فكان كلُّ
            // بحثٍ يجرّ آلافَ الصفوف بلا سبب.
            $like = $this->likeTerm($search);

            // كلُّ شروط البحث في مجموعةٍ واحدة: لو خرج أحدُها منها
            // لصار «أو» يشمل كلَّ المحادثات فيُبطل ترشيحَ الحالة بعده
            $query->where(function ($q) use ($like, $digits) {
                $q->whereHas('contact', function ($c) use ($like, $digits) {
                    $c->where(function ($inner) use ($like, $digits) {
                        $inner->where('profile_name', 'like', '%' . $like . '%');

                        if ($digits !== '') {
                            $inner->orWhere('wa_id', 'like', '%' . $digits . '%');
                        }

                        $inner->orWhereHas('client', fn ($cl) => $cl->where('name', 'like', '%' . $like . '%'));
                    });
                })->orWhereHas('messages', function ($m) use ($like) {
                    // الملاحظات الداخلية تُبحث أيضاً: هي جزءٌ من سجلّ
                    // الفريق، ولا تخرج من هذه الشاشة إلى أحد
                    $m->where('body', 'like', '%' . $like . '%');
                });
            });
        }

        $userId = (int) $request->user()->id;

        $query = match ($filter) {
            'unread' => $query->where('unread_count', '>', 0),
            'mine' => $query->where('assigned_to', $userId),
            'unassigned' => $query->whereNull('assigned_to'),
            'closed' => $query->where('status', WhatsAppConversation::STATUS_CLOSED),
            'open' => $query->where('status', WhatsAppConversation::STATUS_OPEN),
            default => $query,
        };

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // آخرُ رسالةٍ لكل محادثة — خمسةٌ وعشرون صفّاً لا كلُّ الخيوط.
        //
        // ═══ العطل الذي وُضع له ═══
        //
        // كان الاستعلامُ يجلب كلَّ رسائل المحادثات الخمس والعشرين ثمّ
        // يُرشّح في PHP بـunique(). مكتبٌ خيوطُه طويلة يحمّل عشراتِ
        // آلاف الصفوف إلى الذاكرة ليرسم خمسةً وعشرين سطرَ معاينة —
        // وتزداد كلَّ يوم. الآن: معرّفاتُ آخر الرسائل أوّلاً، ثمّ صفوفُها
        // وحدها. ويعمل على MySQL وSQLite معاً.
        $lastIds = WhatsAppMessage::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->groupBy('conversation_id')
            ->pluck(DB::raw('MAX(id)'));

        $lastMessages = WhatsAppMessage::query()
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('conversation_id');

        return view('whatsapp.index', [
            'conversations' => $conversations,
            'lastMessages' => $lastMessages,
            'filter' => $filter,
            'search' => $search,
            'snapshot' => WhatsAppSettings::snapshot(),
            'counts' => [
                'all' => WhatsAppConversation::count(),
                'unread' => WhatsAppConversation::where('unread_count', '>', 0)->count(),
                'mine' => WhatsAppConversation::where('assigned_to', $userId)->count(),
                'unassigned' => WhatsAppConversation::whereNull('assigned_to')->count(),
            ],
        ]);
    }

    public function show(Request $request, WhatsAppConversation $conversation): View
    {
        $conversation->load(['contact.client', 'assignee', 'case']);

        // أحدثُ ثلاثمئة ثمّ تُقلَب للعرض — لا أقدمُ ثلاثمئة.
        //
        // ‏orderBy('id')->limit(300) يعني أنّ خيطاً تجاوز ثلاثمئة رسالة
        // يعرض أوائلَها إلى الأبد ولا يرى المحامي رسالةَ اليوم أصلاً.
        $messages = $conversation->messages()
            ->with(['sender', 'document'])
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->reverse()
            ->values();

        // فتحُ المحادثة يُصفّر غيرَ المقروء ويعلّم الوارد مقروءاً عند
        // العميل — علامةُ القراءة أدبٌ مع من ينتظر، وهي مجّانيّة
        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
            $this->markReadUpstream($messages);
        }

        return view('whatsapp.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'templates' => WhatsAppTemplate::where('status', WhatsAppTemplate::APPROVED)
                ->orderBy('name')->get(),
            'cases' => $this->casesFor($conversation),
            // بلا سقفٍ صامت: مكتبٌ تجاوز خمسمئة موكّل كان يعجز عن ربط
            // محادثةٍ بالباقين ولا تقول له الشاشةُ لماذا. والقائمةُ
            // محدودةٌ بموكّلي مكتبٍ واحد، وعمودان منها فقط.
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            // أدوارُ هذا النظام: developer/admin/lawyer/staff/client —
            // ولا وجود لدور «employee». كان اسمُه هنا يعني أنّ موظّفي
            // المكتب لا يظهرون في قائمة الإسناد إطلاقاً.
            'staff' => User::where('is_active', true)
                ->whereIn('role', ['admin', 'lawyer', 'staff'])
                ->orderBy('name')->get(['id', 'name']),
            'snapshot' => WhatsAppSettings::snapshot(),
        ]);
    }

    /**
     * إرسالُ ردّ.
     *
     * ═══ حارسُ النافذة ═══
     *
     * خارج نافذة الأربع والعشرين ساعة ترفض Meta الردَّ الحرّ بخطأ
     * ‏131047. ولولا المنعُ هنا لظهرت الرسالة في الخيط ثمّ فشلت بعد
     * ثوانٍ — فيظنّ المحامي أنّه أجاب موكّله وهو لم يفعل.
     */
    public function send(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required_without:template', 'nullable', 'string', 'max:4000'],
            'template' => ['nullable', 'string', 'max:120'],
            'params' => ['nullable', 'array', 'max:10'],
            'params.*' => ['nullable', 'string', 'max:200'],
        ]);

        if (!WhatsAppManager::isConnected()) {
            return back()->with('error', 'لم يُربط رقم واتساب لهذا المكتب بعد.');
        }

        $contact = $conversation->contact;

        if ($contact && !$contact->acceptsNotifications()) {
            return back()->with('error', 'طلب هذا الرقم إيقاف المراسلة — لا تُرسَل إليه رسائل.');
        }

        if (filled($validated['template'] ?? null)) {
            $template = WhatsAppTemplate::where('name', $validated['template'])->first();

            if (!$template || !$template->isApproved()) {
                return back()->with('error', 'هذا القالب غير معتمَد من Meta بعد.');
            }

            $params = array_values(array_filter((array) ($validated['params'] ?? []), 'filled'));

            if (count($params) !== $template->variableCount()) {
                return back()->with('error', 'عدد قيم القالب لا يطابق ما اعتُمد عند Meta.');
            }

            $message = $this->inbox->queueOutgoing(
                $conversation,
                'template',
                json_encode($params, JSON_UNESCAPED_UNICODE),
                $request->user(),
                ['template_name' => $template->name],
            );
        } else {
            if (!$conversation->windowOpen()) {
                return back()->with('error', 'مضت أربعٌ وعشرون ساعة على آخر رسالة من العميل — استخدم قالباً معتمَداً.');
            }

            $message = $this->inbox->queueOutgoing(
                $conversation,
                'text',
                (string) $validated['body'],
                $request->user(),
            );
        }

        SendWhatsAppMessage::dispatch($message->id);

        $this->audit('whatsapp_message_sent', $conversation, ['message_id' => $message->id]);

        return back()->with('success', 'أُضيفت الرسالة إلى قائمة الإرسال.');
    }

    /** ملاحظةٌ داخلية — لا تُرسَل إلى العميل أبداً. */
    public function note(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->inbox->addInternalNote($conversation, $validated['body'], $request->user());

        return back()->with('success', 'حُفظت الملاحظة الداخلية — لم تُرسَل للعميل.');
    }

    public function assign(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // موظّفٌ معطَّل لا يُسنَد إليه: محادثةٌ تُسنَد لمن لا يدخل النظام
        // تبقى بلا ردٍّ وتبدو متابَعة
        if (filled($validated['assigned_to'] ?? null)) {
            $target = User::find($validated['assigned_to']);

            if (!$target || !$target->is_active || $target->role === 'client') {
                return back()->with('error', 'لا يمكن إسناد المحادثة إلى هذا الحساب.');
            }
        }

        $conversation->forceFill(['assigned_to' => $validated['assigned_to'] ?? null])->save();
        $this->audit('whatsapp_conversation_assigned', $conversation, ['assigned_to' => $validated['assigned_to'] ?? null]);

        return back()->with('success', 'حُدِّث إسناد المحادثة.');
    }

    public function linkClient(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $contact = $conversation->contact;

        if (!$contact) {
            return back()->with('error', 'جهة الاتصال غير موجودة.');
        }

        $contact->forceFill(['client_id' => $validated['client_id'] ?? null])->save();

        // فكُّ ربط الموكّل يفكّ ربطَ القضية معه: قضيّةٌ باقيةٌ على
        // محادثةٍ لموكّلٍ آخر تعرض ملفَّ قضيّةٍ لمن لا يملكها
        if (blank($validated['client_id'] ?? null)) {
            $conversation->forceFill(['case_id' => null])->save();
        }

        $this->audit('whatsapp_contact_linked', $conversation, ['client_id' => $validated['client_id'] ?? null]);

        return back()->with('success', 'حُدِّث ربط جهة الاتصال بالموكّل.');
    }

    /**
     * ربطُ المحادثة بقضيّة.
     *
     * ═══ الحارس ═══
     *
     * القضيّةُ يجب أن تكون قضيّةَ الموكّل المرتبط بهذه المحادثة. ولولا
     * ذلك لأمكن ربطُ محادثةِ موكّلٍ بقضيّةِ موكّلٍ آخر — فتظهر مستنداتُ
     * قضيّةٍ ليست له في سياق محادثته، ويُحفظ ما يرسله في ملفّ غيره.
     */
    public function linkCase(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'case_id' => ['nullable', 'integer', 'exists:cases,id'],
        ]);

        $caseId = $validated['case_id'] ?? null;

        if ($caseId !== null) {
            $clientId = $conversation->contact?->client_id;

            if ($clientId === null) {
                return back()->with('error', 'اربط المحادثة بموكّل أولاً، ثم اختر إحدى قضاياه.');
            }

            $case = LegalCase::where('id', $caseId)->where('client_id', $clientId)->first();

            if (!$case) {
                return back()->with('error', 'هذه القضية ليست من قضايا الموكّل المرتبط بالمحادثة.');
            }
        }

        $conversation->forceFill(['case_id' => $caseId])->save();
        $this->audit('whatsapp_conversation_linked_case', $conversation, ['case_id' => $caseId]);

        return back()->with('success', 'حُدِّث ربط المحادثة بالقضية.');
    }

    /**
     * حفظُ وسيطٍ وارد في ملف القضية.
     *
     * الرسالةُ يجب أن تكون من هذه المحادثة، والقضيّةُ قضيّةَ موكّلها.
     * فحصُ الاثنين معاً يمنع تمرير معرّفٍ من محادثةٍ أخرى في الطلب.
     */
    public function saveDocument(Request $request, WhatsAppMessage $message): RedirectResponse
    {
        $validated = $request->validate([
            'case_id' => ['required', 'integer', 'exists:cases,id'],
            'case_folder_id' => ['nullable', 'integer', 'exists:case_folders,id'],
            'title' => ['nullable', 'string', 'max:190'],
        ]);

        $conversation = $message->conversation;
        $clientId = $conversation?->contact?->client_id;

        if ($clientId === null) {
            return back()->with('error', 'اربط المحادثة بموكّل أولاً ليُحفظ المستند في إحدى قضاياه.');
        }

        $case = LegalCase::where('id', $validated['case_id'])->where('client_id', $clientId)->first();

        if (!$case) {
            return back()->with('error', 'هذه القضية ليست من قضايا الموكّل المرتبط بالمحادثة.');
        }

        if (!$message->hasMedia()) {
            return back()->with('error', 'لا يوجد ملف في هذه الرسالة.');
        }

        if ($message->document_id !== null) {
            return back()->with('error', 'هذا الملف محفوظ في ملف القضية بالفعل.');
        }

        $document = $this->inbox->saveMediaAsDocument(
            $message,
            $case->id,
            $request->user(),
            $validated['case_folder_id'] ?? null,
            $validated['title'] ?? null,
        );

        if (!$document) {
            return back()->with('error', 'تعذّر جلب الملف من واتساب — قد تكون مهلة تنزيله انتهت.');
        }

        $this->audit('whatsapp_document_saved', $conversation, [
            'document_id' => $document->id,
            'case_id' => $case->id,
        ]);

        return back()->with('success', 'حُفظ الملف في ملف القضية.');
    }

    /** تحويلٌ إلى موظّف — يوقف الردّ الآلي على هذه المحادثة. */
    public function handoff(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $conversation->forceFill([
            'handoff_at' => now(),
            'assigned_to' => $conversation->assigned_to ?? $request->user()->id,
        ])->save();

        $this->audit('whatsapp_conversation_handoff', $conversation, []);

        return back()->with('success', 'أُوقف الردّ الآلي — المحادثة الآن لموظّف.');
    }

    public function close(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        $open = $conversation->status === WhatsAppConversation::STATUS_CLOSED;

        $conversation->forceFill([
            'status' => $open ? WhatsAppConversation::STATUS_OPEN : WhatsAppConversation::STATUS_CLOSED,
        ])->save();

        return back()->with('success', $open ? 'أُعيد فتح المحادثة.' : 'أُغلقت المحادثة.');
    }

    // ── داخلي ────────────────────────────────────────────────────

    /**
     * تهريبُ محارف LIKE الخاصّة.
     *
     * بحثٌ عن «%» كان يطابق كلَّ المحادثات، و«_» يطابق أيَّ محرف —
     * فيرى الموظّف نتائجَ لا علاقة لها بما كتب ويظنّ البحث معطوباً.
     */
    private function likeTerm(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }

    /** قضايا الموكّل المرتبط وحده — لا كلُّ قضايا المكتب. */
    private function casesFor(WhatsAppConversation $conversation)
    {
        $clientId = $conversation->contact?->client_id;

        if ($clientId === null) {
            return collect();
        }

        return LegalCase::where('client_id', $clientId)
            ->orderByDesc('id')
            ->get(['id', 'case_number', 'title']);
    }

    /** علامةُ القراءة عند العميل — إخفاقُها لا يُفشل فتحَ الصفحة. */
    private function markReadUpstream($messages): void
    {
        $provider = WhatsAppManager::provider();

        if (!$provider) {
            return;
        }

        $last = $messages->last(fn ($m) => $m->isInbound() && filled($m->wamid));

        if ($last) {
            try {
                $provider->markRead((string) $last->wamid);
            } catch (\Throwable) {
                // لا شيء — العلامة تحسينٌ لا وظيفة
            }
        }
    }

    private function audit(string $action, ?WhatsAppConversation $conversation, array $data): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => WhatsAppConversation::class,
                'model_id' => $conversation?->id,
                'old_values' => null,
                // لا نصَّ رسالةٍ في السجلّ: هو مراسلةُ موكّل، والسجلّ
                // يجيب «من فعل ماذا ومتى» لا «ماذا قال الموكّل»
                'new_values' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable) {
            // السجلّ خبرٌ عن الحدث لا الحدثُ نفسه
        }
    }
}
