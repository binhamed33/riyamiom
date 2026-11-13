<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\ClientPortal\CaseTimeline;
use App\Services\ClientPortal\ClientAuthenticator;
use App\Services\ClientPortal\ClientCaseGateway;
use App\Support\ClientPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بوابة عملاء مُداوَلة.
 *
 * كل قراءة تمرّ من ClientCaseGateway المقيَّد بالعميل الحالي. لا يوجد
 * في هذا المتحكّم مسار يستقبل نموذجاً مُحقوناً من الرابط، فلا مكان
 * يُنسى فيه فحص الملكية.
 */
class ClientAccessController extends Controller
{
    public function __construct(private ClientAuthenticator $auth)
    {
    }

    // ------------------------------------------------------------ الدخول

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (!ClientPortal::enabled()) {
            return view('client-portal.disabled');
        }

        if ($this->auth->current($request)) {
            return redirect()->route('client.portal.home');
        }

        // ═══ رقمٌ قديمٌ في الجلسة لا يفتح خطوةَ الرمز ═══
        //
        // الجلسةُ تعيش ساعات والرمزُ خمسَ دقائق. فمن طلب رمزاً صباحاً وعاد مساءً
        // وجد «أدخل الرمزَ الذي وصلك» ولم يُرسَل في زيارته شيء — فينتظر ما لن يصل.
        $at = (int) $request->session()->get('portal_otp_at', 0);

        if ($request->session()->has('portal_otp_phone')
            && now()->timestamp - $at > \App\Services\ClientPortal\PortalOtp::TTL_SECONDS) {
            $request->session()->forget(['portal_otp_phone', 'portal_otp_at']);
        }

        return view('client-portal.login');
    }

    /**
     * طلبُ رمز الدخول على واتساب.
     *
     * الهاتفُ يُحفظ في الجلسة لا في النموذج التالي: التحقّقُ يقرأ
     * رقمَ الجلسة، فلا يستطيع زائرٌ أن يطلب رمزاً لرقمٍ ويتحقّق
     * برقمٍ آخر.
     */
    public function otpSend(Request $request): RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $request->validate(['phone' => 'required|string|max:20']);

        $result = \App\Services\ClientPortal\PortalOtp::request((string) $request->input('phone'));

        if ($result['ok']) {
            $request->session()->put('portal_otp_phone', (string) $request->input('phone'));
            $request->session()->put('portal_otp_at', now()->timestamp);

            return redirect()->route('client.access')->with('portal_notice', $result['message']);
        }

        // لم يخرج رمز (غيرُ مسجَّل، مشترك، تعذّر الإرسال…): يعود الموكّلُ إلى حقل
        // الرقم ومعه السبب — لا يقف عند «أدخل الرمز» ينتظر ما لن يصل. إلا إن كان
        // في يد رقمه نفسِه رمزٌ سابقٌ صالح (طلب غيرَه قبل الدقيقتين، أو أخفق الثاني):
        // فيبقى عند خطوة الرمز ليُدخل ما وصله
        $samePhone = Client::normalizePhone((string) $request->session()->get('portal_otp_phone', ''))
            === Client::normalizePhone((string) $request->input('phone'));

        if (!($samePhone && \App\Services\ClientPortal\PortalOtp::hasLiveCode((string) $request->input('phone')))) {
            $request->session()->forget(['portal_otp_phone', 'portal_otp_at']);
        }

        return redirect()->route('client.access')->with('portal_error', $result['message']);
    }

    /**
     * رجوعٌ لتغيير الرقم.
     *
     * الهاتفُ يبقى في الجلسة حتى ينجح التحقّق، وذلك صحيحٌ للأمان —
     * لكنه كان بلا بابِ خروج: من أخطأ رقمَه وقف أمام حقل رمزٍ لن
     * يصله شيءٌ فيه، بلا رجوعٍ إلا انتهاءُ الجلسة. الرمزُ المخزَّن
     * لا يُحرق هنا: صلاحيتُه خمسُ دقائقَ تُنهيه، والتحقّقُ لا يقرأ
     * إلا هاتفَ الجلسة الذي مُحي للتوّ.
     */
    public function otpReset(Request $request): RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $request->session()->forget(['portal_otp_phone', 'portal_otp_at']);

        return redirect()->route('client.access', ['otp' => 1]);
    }

    public function otpVerify(Request $request): RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $request->validate(['code' => 'required|string|max:10']);

        $phone = (string) $request->session()->get('portal_otp_phone', '');

        if ($phone === '') {
            return redirect()->route('client.access')
                ->with('portal_error', 'ابدأ بطلب الرمز أولاً.');
        }

        $result = \App\Services\ClientPortal\PortalOtp::verify($phone, (string) $request->input('code'));

        if (!$result['ok']) {
            return redirect()->route('client.access')
                ->with('portal_error', $result['message']);
        }

        $request->session()->forget(['portal_otp_phone', 'portal_otp_at']);

        // ملفٌّ واحد على الرقم: يُفتح مباشرةً. وأكثر: يختار صاحبُ الهاتف أيَّها.
        // واختياراتُ تحقّقٍ سابقٍ في الجلسة نفسِها تُمحى: هذا دخولٌ آخر برقمٍ آخر
        $request->session()->forget(self::CHOICES);

        if ($result['clients']->count() === 1) {
            $this->auth->establish($request, $result['client']);

            return redirect()->route('client.portal.home')->with('portal_welcome', true);
        }

        // الاختياراتُ في الجلسة لا في الرابط: لا يُفتح ملفٌّ لم يثبت الرمزُ أنّه على
        // رقم صاحبه. وتُبدَّل الجلسةُ هنا كما عند الدخول (Session Fixation)
        $request->session()->regenerate();
        $request->session()->put(self::CHOICES, [
            'wa_id' => $result['wa_id'],
            'ids' => $result['clients']->pluck('id')->all(),
        ]);

        return redirect()->route('client.access.choose');
    }

    /** مفتاحُ ملفّات الرقم الذي ثبت بالرمز — ليختار صاحبُه بينها ويبدّل لاحقاً. */
    public const CHOICES = 'portal_choices';

    /** ملفّاتُ رقمٍ ثبت بالرمز: يختار صاحبُه أيَّها يفتح. */
    public function chooseFile(Request $request): View|RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $clients = $this->choices($request);
        $current = $this->auth->current($request);

        if ($clients->isEmpty()) {
            return redirect()->route('client.access')->with('portal_error', 'انتهت الجلسة — اطلب رمزاً جديداً.');
        }

        // لم يبقَ على الرقم إلا ملفٌّ واحد (نقل المكتبُ رقمَ الآخر): يُفتح — صاحبُ
        // الهاتف أثبت ملكيّتَه، ولا يُطلب منه رمزٌ جديدٌ قد تحجزه الدقيقتان
        if ($clients->count() === 1) {
            if (!$current || $current->id !== $clients->first()->id) {
                $choices = $request->session()->get(self::CHOICES);
                $this->auth->establish($request, $clients->first());
                $request->session()->put(self::CHOICES, $choices);
            }

            return redirect()->route('client.portal.home');
        }

        // ‏client للتخطيط: من دخل ثمّ فتح «تبديل الملفّ» يرى رأسَ البوابة وزرَّ الخروج
        return view('client-portal.choose', [
            'clients' => $clients,
            'current' => $current,
            'client' => $current,
        ]);
    }

    public function choose(Request $request): RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $request->validate(['client_id' => 'required|integer']);

        $client = $this->choices($request)->firstWhere('id', (int) $request->input('client_id'));

        // ملفٌّ ليس بين ملفّات الرقم الذي ثبت: لا يُفتح ولو عُرف رقمُه
        if (!$client) {
            return redirect()->route('client.access')->with('portal_error', 'انتهت الجلسة — اطلب رمزاً جديداً.');
        }

        $choices = $request->session()->get(self::CHOICES);
        $this->auth->establish($request, $client);
        // تبقى الاختياراتُ في الجلسة ليبدّل صاحبُها بين ملفّاته دون رمزٍ جديد
        $request->session()->put(self::CHOICES, $choices);

        return redirect()->route('client.portal.home')->with('portal_welcome', true);
    }

    /**
     * ملفّاتُ الرقم الذي ثبت — وما زالت عليه الآن.
     *
     * ملفٌّ نقل المكتبُ عنه الرقمَ أو حذفه بعد التحقّق لا يُعرض ولا يُفتح ولو بقي
     * معرّفُه في الجلسة: الصلاحيةُ ملكيّةُ الهاتف، والهاتفُ لم يعد عليه.
     *
     * @return \Illuminate\Support\Collection<int, Client>
     */
    private function choices(Request $request): \Illuminate\Support\Collection
    {
        $choices = $request->session()->get(self::CHOICES);

        return \App\Services\ClientPortal\PortalOtp::filesOn(
            array_map('intval', (array) ($choices['ids'] ?? [])),
            (string) ($choices['wa_id'] ?? ''),
        );
    }

    /** أيُبدَّل الملفّ؟ — رقمٌ ثبت وعليه الآن أكثرُ من ملفّ. */
    public function canSwitch(Request $request): bool
    {
        return $this->choices($request)->count() > 1;
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->route('client.access');
    }

    // ------------------------------------------------------------ البوابة

    public function home(Request $request): View
    {
        $gateway = $this->gateway($request);

        $upcoming = $gateway->upcomingSessions(3);

        $client = $this->auth->current($request);

        return view('client-portal.home', [
            'client' => $client,
            'summary' => $gateway->summary(),
            'nextSession' => $upcoming->first(),
            'upcoming' => $upcoming,
            'recent' => $gateway->recentlyUpdated(3),
            'dues' => $gateway->duesSummary(),

            // ═══ مواعيدُ صاحب الجلسة وحدَه ═══
            //
            // الاستعلامُ مقيَّدٌ بمعرّف الموكّل الجالس، لا بما يصل في
            // العنوان: البوابةُ تُقرأ من الجلسة وحدها، فلا يرى أحدٌ
            // موعدَ غيره بتغيير رقمٍ في الرابط.
            'appointments' => $client
                ? \App\Models\Appointment::where('client_id', $client->id)
                    ->upcoming()->with('user:id,name')->limit(5)->get()
                : collect(),
        ]);
    }

    public function cases(Request $request): View
    {
        $gateway = $this->gateway($request);

        return view('client-portal.cases', [
            'client' => $this->auth->current($request),
            'cases' => $gateway->cases()
                ->with(ClientPortal::showsLawyer() ? ['lawyer:id,name'] : [])
                ->orderByDesc('updated_at')
                ->paginate(10),
        ]);
    }

    public function showCase(Request $request, string $case): View
    {
        $gateway = $this->gateway($request);

        // قضية خارج نطاق هذا العميل ببساطة لا تُوجَد
        $legalCase = $gateway->findCase($case);
        abort_unless($legalCase, 404);

        if (ClientPortal::showsLawyer()) {
            $legalCase->load('lawyer:id,name');
        }

        return view('client-portal.case', [
            'client' => $this->auth->current($request),
            'case' => $legalCase,
            'sessions' => $gateway->sessionsFor($legalCase),
            'documents' => $gateway->documentsFor($legalCase),
            'timeline' => app(CaseTimeline::class, ['gateway' => $gateway])->build($legalCase),
            'accounting' => $gateway->accountingFor($legalCase),
        ]);
    }

    /**
     * مركزُ الإشعارات — ما جدَّ في ملفّات هذا الموكّل.
     *
     * والاستعلامُ محصورٌ بمعرّفه من الجلسة لا من العنوان: لا يوجد في
     * هذا المسار معرّفٌ يكتبه المستخدم أصلاً، فلا شيءَ يُغيَّر ليُرى
     * إشعارُ غيره.
     */
    public function notifications(Request $request): View
    {
        $client = $this->auth->current($request);

        $items = \App\Models\ClientNotification::where('client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        // تُعلَّم مقروءةً عند الفتح: الوسمُ يعني «جدَّ ما لم تره»،
        // وبقاؤه بعد القراءة يجعله ضجيجاً يُتجاهَل
        \App\Models\ClientNotification::where('client_id', $client->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('client-portal.notifications', [
            'client' => $client,
            'items' => $items,
        ]);
    }

    /**
     * تنزيل مستند.
     *
     * الصلاحية تُفحص في الخادم لا بإخفاء الزر: معرّف مستند لا يخصّ
     * قضايا هذا العميل، أو غير معلَّم للعرض، أو خاص — يعطي ٤٠٤.
     */
    public function document(Request $request, string $document): StreamedResponse
    {
        $doc = $this->gateway($request)->findDocument($document);
        abort_unless($doc, 404);

        $disk = \Illuminate\Support\Facades\Storage::disk('private');
        abort_unless($doc->file_path && $disk->exists($doc->file_path), 404);

        $name = $doc->title ?: basename($doc->file_path);
        $extension = pathinfo($doc->file_path, PATHINFO_EXTENSION);

        return $disk->download($doc->file_path, $name . ($extension ? '.' . $extension : ''), [
            // لا يُفسَّر الملف شيئاً آخر مهما كان امتداده
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function account(Request $request): View
    {
        return view('client-portal.account', [
            'client' => $this->auth->current($request),
        ]);
    }

    private function gateway(Request $request): ClientCaseGateway
    {
        $client = $this->auth->current($request);
        abort_unless($client, 403);

        return ClientCaseGateway::for($client);
    }
}
