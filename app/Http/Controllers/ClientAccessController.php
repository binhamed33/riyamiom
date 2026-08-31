<?php

namespace App\Http\Controllers;

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

        return view('client-portal.login', [
            'challenge' => $this->auth->pendingChallenge($request),
        ]);
    }

    /** الخطوة الأولى: رقم الهوية */
    public function lookup(Request $request): RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $request->validate(['national_id' => 'required|string|max:40']);

        $result = $this->auth->beginLookup($request, $request->input('national_id'));

        if ($result['locked']) {
            return back()->with('portal_error', __('portal.login.locked', [
                'minutes' => max(1, (int) ceil(($result['retry_after'] ?? 60) / 60)),
            ]));
        }

        if (!$result['ok']) {
            // رسالة واحدة: لا يُعرف من الرد أوُجد رقم الهوية أم لا
            return back()->with('portal_error', __('portal.login.failed'));
        }

        return redirect()->route('client.access')->with('portal_step', 2);
    }

    /** الخطوة الثانية: آخر ٣ أرقام من الهاتف */
    public function verify(Request $request): RedirectResponse
    {
        abort_unless(ClientPortal::enabled(), 404);

        $request->validate(['digits' => 'required|string|max:10']);

        $result = $this->auth->verify($request, $request->input('digits'));

        if ($result['ok']) {
            return redirect()->route('client.portal.home')->with('portal_welcome', true);
        }

        if ($result['locked']) {
            return redirect()->route('client.access')->with('portal_error', __('portal.login.locked', [
                'minutes' => max(1, (int) ceil(($result['retry_after'] ?? 60) / 60)),
            ]));
        }

        if ($result['expired']) {
            return redirect()->route('client.access')->with('portal_error', __('portal.login.expired'));
        }

        return back()->with('portal_error', __('portal.login.failed'));
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

        return view('client-portal.home', [
            'client' => $this->auth->current($request),
            'summary' => $gateway->summary(),
            'nextSession' => $upcoming->first(),
            'upcoming' => $upcoming,
            'recent' => $gateway->recentlyUpdated(3),
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
