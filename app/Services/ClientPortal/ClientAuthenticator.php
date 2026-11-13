<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use Illuminate\Http\Request;

/**
 * مصادقة بوابة العملاء — جلسةُ الموكّل.
 *
 * الدخولُ يكون برمزٍ يصل على واتساب (PortalOtp) أو برابطٍ موقَّعٍ يرسله
 * المكتب (ClientLinkController). كلاهما يتحقّق من صاحب الرقم ثم يستدعي
 * establish() ليفتح الجلسة. وهذا الصنفُ لا يعرف «كيف» تحقّقنا — يحفظ
 * الجلسةَ ويقرؤها وينهيها فقط.
 *
 * ═══ لماذا لم يعد هنا دخولٌ برقم الهويّة ═══
 *
 * رقمُ الهويّة في عُمان يُكتب في العقود ويُعطى لجهاتٍ كثيرة، فليس سرّاً
 * يُبنى عليه دخول. وآخرُ أرقامٍ من الهاتف سرٌّ ضعيفٌ يُخمَّن. فأُلغي البابُ
 * كلُّه، وصار الدخولُ برمزٍ يصل على هاتف الموكّل نفسِه لا غير.
 */
class ClientAuthenticator
{
    public const SESSION_CLIENT = 'client_access_id';
    public const SESSION_NAME = 'client_access_name';
    public const SESSION_AT = 'client_access_at';

    /**
     * فتحُ جلسةٍ لموكّلٍ تحقّقنا منه (رمزُ واتساب أو رابطٌ موقَّع).
     *
     * ═══ لماذا regenerate ═══
     *
     * معرّفُ الجلسة قبل الدخول معرّفُ زائر، وقد يكون أحدٌ زرعه في متصفّح
     * الموكّل (Session Fixation). فيُبدَّل عند رفع الصلاحية.
     */
    public function establish(Request $request, Client $client): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            self::SESSION_CLIENT => $client->id,
            self::SESSION_NAME => $client->name,
            self::SESSION_AT => now()->timestamp,
        ]);
    }

    /** العميل الحالي — أو null. لا يُقرأ من غير الجلسة. */
    public function current(Request $request): ?Client
    {
        $id = $request->session()->get(self::SESSION_CLIENT);

        return $id ? Client::find($id) : null;
    }

    /**
     * نسيانُ مفتاح البوابة وحدَه — بلا مساسٍ ببقيّة الجلسة.
     *
     * للحارس: زائرٌ يطرق بابَ البوابة بلا جلسةِ موكّلٍ قد يكون موظّفاً
     * مسجَّلَ الدخول، وإفراغُ جلسته إخراجٌ له من حسابه.
     */
    public function forget(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_CLIENT,
            self::SESSION_NAME,
            self::SESSION_AT,
        ]);
    }

    public function logout(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_CLIENT,
            self::SESSION_NAME,
            self::SESSION_AT,
        ]);

        // إبطال فعلي: الجلسة القديمة لا تعود صالحة حتى لو نُسخ معرّفها
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
