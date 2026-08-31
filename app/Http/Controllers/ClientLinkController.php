<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Services\ClientPortal\ClientAuthenticator;
use App\Services\ClientPortal\PortalLinks;
use App\Support\ClientPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * فتحُ رابطٍ أُرسل في واتساب.
 *
 * ═══ ما يفعله ═══
 *
 * يتحقّق من الرمز، ويفتح جلسةَ بوابةٍ عاديّةً لصاحبه، ويختم الرمزَ
 * مستعمَلاً، ثمّ يوجّه إلى الصفحة المقصودة.
 *
 * ═══ وما لا يفعله ═══
 *
 * لا يوسّع صلاحيةَ أحد. الجلسةُ الناتجة نفسُ ما ينشئه دخولٌ بالهوية
 * وآخرِ ثلاثة أرقام، وكلُّ صفحةٍ بعده تمرّ بالبوّابة التي تحصر
 * الاستعلام في قضايا هذا الموكّل. فتغييرُ رقمٍ في العنوان بعد الدخول
 * لا يُظهر ملفَّ غيره — يُعطي ٤٠٤ كما لو لم يوجد.
 *
 * ═══ ورسائلُ الرفض لا تُفصح ═══
 *
 * «انتهت صلاحية الرابط» و«استُعمل من قبل» و«لا وجود له» ثلاثتُها
 * جوابٌ واحدٌ للزائر: رابطٌ لا يعمل، ادخل بهويّتك. والتفريقُ بينها
 * يقول لمن يجرّب رموزاً أيُّها كان صحيحاً يوماً.
 */
class ClientLinkController extends Controller
{
    public function __construct(private ClientAuthenticator $auth)
    {
    }

    public function open(Request $request, string $token): RedirectResponse
    {
        if (!ClientPortal::enabled()) {
            return redirect()->route('client.access')
                ->with('portal_error', __('portal.login.disabled'));
        }

        $link = PortalLinks::find($token);

        if (!$link || !$link->usable()) {
            return $this->refuse();
        }

        $client = Client::find($link->client_id);

        if (!$client) {
            return $this->refuse();
        }

        // يُختم مستعمَلاً قبل فتح الجلسة: لو انقطع الطلبُ بينهما بقي
        // الرمزُ محروقاً — وحرقُ رمزٍ صحيحٍ أهونُ من إبقاء واحدٍ
        // مستعمَلٍ يعمل مرّتين
        $link->forceFill([
            'used_at' => now(),
            'used_ip' => $request->ip(),
        ])->save();

        $this->auth->establish($request, $client);
        $this->audit($client, $link->target);

        return redirect()->to($this->destination((string) $link->target, $link->target_id));
    }

    private function refuse(): RedirectResponse
    {
        return redirect()->route('client.access')
            ->with('portal_error', 'انتهت صلاحية الرابط أو سبق استعماله. ادخل برقم هويّتك للاطّلاع على قضاياك.');
    }

    /** وجهةٌ مفكّكة ⇐ عنوانٌ يُبنى الآن بالنطاق الحالي. */
    private function destination(string $target, ?int $id): string
    {
        return match ($target) {
            'case', 'sessions', 'documents', 'timeline' => $id
                ? route('client.portal.case', $id) . '#' . $target
                : route('client.portal.cases'),
            'billing' => route('client.portal.home') . '#billing',
            'notifications' => route('client.portal.notifications'),
            default => route('client.portal.home'),
        };
    }

    private function audit(Client $client, string $target): void
    {
        try {
            AuditLog::create([
                'user_id' => null,
                'action' => 'client_portal_link_opened',
                'model_type' => Client::class,
                'model_id' => $client->id,
                'old_values' => null,
                'new_values' => json_encode(['target' => $target], JSON_UNESCAPED_UNICODE),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable) {
            // السجلّ خبرٌ عن الحدث لا الحدثُ نفسه
        }
    }
}
