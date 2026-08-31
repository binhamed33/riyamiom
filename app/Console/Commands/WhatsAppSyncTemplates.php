<?php

namespace App\Console\Commands;

use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppManager;
use App\Support\WhatsAppSettings;
use Illuminate\Console\Command;

/**
 * جلبُ قوالب واتساب من Meta وتحديثُ صورتها عندنا.
 *
 * ═══ العطل الذي يمنعه ═══
 *
 * حالةُ القالب تتغيّر عند Meta بلا إشعارٍ لنا: قالبٌ كان «معتمَداً»
 * يُوقَف لبلاغاتٍ عن رسائل غير مرغوبة، أو يُحذف من واجهة Meta فيبقى
 * عندنا معتمَداً في القائمة. فيختاره المكتبُ لتذكير الجلسات، ويخفق كلُّ
 * إرسالٍ بخطأ 132001 — ويرى المحامي «أخفق الإرسال» بلا سببٍ يفهمه،
 * والموكّل لا يصله تذكيرُ جلسته.
 *
 * فالمزامنةُ اليومية تجعل ما نعرضه مرآةً لما عند Meta لا حكماً منّا.
 *
 * ═══ ولماذا لا يعود بفشل ═══
 *
 * يعمل مجدولاً في كل مكتب، وأكثرُ المكاتب لم تربط رقمها بعد. وأمرٌ
 * مجدولٌ يحمرّ كلَّ ليلةٍ لسببٍ مشروع يُدرَّب صاحبُ النظام على تجاهله،
 * فيضيع فيه الإخفاقُ الحقيقيّ يوم يقع.
 */
class WhatsAppSyncTemplates extends Command
{
    protected $signature = 'whatsapp:sync-templates';

    protected $description = 'مزامنة قوالب واتساب المعتمَدة من Meta إلى جدول القوالب';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>مزامنة قوالب واتساب</>');
        $this->line(str_repeat('─', 52));

        if (! WhatsAppManager::isConnected()) {
            $this->line('لم يُربط رقم واتساب لهذا المكتب بعد — لا شيء يُزامَن.');
            $this->line('الربط من: الإعدادات ← واتساب الأعمال.');

            return self::SUCCESS;
        }

        // القوالب تُقرأ من حساب الأعمال (WABA) لا من الرقم: مكتبٌ ضبط
        // الرمزَ ومعرّفَ الرقم ونسي معرّفَ الحساب يرى قائمةً فارغةً بلا
        // سبب — فيُقال له السببُ صراحةً بدل «لا قوالب».
        if (! filled(WhatsAppSettings::wabaId())) {
            $this->line('<fg=yellow>معرّف حساب الأعمال (WABA ID) غير مضبوط</> — والقوالب تُقرأ منه.');
            $this->line('انسخه من Meta Business Suite ← إعدادات حساب واتساب للأعمال.');

            return self::SUCCESS;
        }

        $provider = WhatsAppManager::provider();

        if (! $provider) {
            $this->line('لا مزوّد مضبوط — راجع config/whatsapp.php.');

            return self::SUCCESS;
        }

        $fetched = $provider->fetchTemplates();

        if ($fetched === []) {
            $error = $provider->getLastError();

            if (filled($error)) {
                // يُكتب في حالة الخدمة كي يظهر في الإعدادات وفي
                // whatsapp:doctor — لا في سجلٍّ لا يقرؤه أحد
                WhatsAppSettings::recordError((string) $error);
                $this->line('<fg=red>تعذّرت المزامنة:</> ' . $error);

                return self::SUCCESS;
            }

            // القائمةُ الفارغة بلا خطأٍ محفوظ تحتمل أمرين: حسابٌ بلا
            // قوالب بعد، أو انقطاعُ شبكةٍ ابتلعه المزوّد في السجلّ ولم
            // يرفعه إلينا. فلا يُدَّعى أحدُهما — الادّعاءُ «لا قوالب»
            // يُطمئن مكتباً قوالبُه موجودةٌ والخادمُ لا يبلغ Meta أصلاً.
            $this->line('لم تصل قوالب من Meta.');
            $this->line('إمّا أنّ الحساب بلا قوالب بعد — وتُنشأ من Meta Business Suite —');
            $this->line('أو أنّ الاتصال تعذّر: php artisan whatsapp:doctor --probe');

            return self::SUCCESS;
        }

        $rows = [];
        $created = 0;
        $updated = 0;
        $seen = [];

        foreach ($fetched as $entry) {
            $entry = (array) $entry;
            $name = trim((string) ($entry['name'] ?? ''));
            $language = trim((string) ($entry['language'] ?? 'ar'));

            // قالبٌ بلا اسمٍ لا يُرسَل به شيء، وإدراجُه يكسر قيد
            // الفرادة (name+language) بصفوفٍ فارغة متكرّرة
            if ($name === '') {
                continue;
            }

            if ($language === '') {
                $language = 'ar';
            }

            $body = $this->bodyText($entry);

            $template = WhatsAppTemplate::firstOrNew([
                'name' => $name,
                'language' => $language,
            ]);

            $isNew = ! $template->exists;

            $template->fill([
                'category' => $this->trimTo($entry['category'] ?? null, 32),
                // حالةُ Meta تُحفظ كما هي بحروفٍ كبيرة: isApproved()
                // تقارن بـ APPROVED، و«approved» بحروفٍ صغيرة كانت
                // ستمرّ الآن وتسقط لو تغيّرت المقارنة يوماً
                'status' => strtoupper($this->trimTo($entry['status'] ?? 'PENDING', 16) ?: 'PENDING'),
                'body' => $body,
                'variables' => $this->variables($body),
                'meta_id' => $this->trimTo($entry['id'] ?? null, 64),
                'synced_at' => now(),
            ])->save();

            $isNew ? $created++ : $updated++;
            $seen[] = $name . '|' . $language;

            $rows[] = [
                $name,
                $language,
                $template->statusLabel(),
                (string) $template->variableCount(),
            ];
        }

        $this->newLine();

        if ($rows === []) {
            $this->line('لا قوالب صالحة في الرد.');

            return self::SUCCESS;
        }

        $this->table(['القالب', 'اللغة', 'الحالة', 'المتغيّرات'], $rows);

        $this->line('  جديد: ' . $created . ' · محدَّث: ' . $updated);

        // صفوفٌ عندنا لم تَرِد في الرد: حُذفت من Meta أو أنّ الرد
        // اقتُطع عند المئة. لا تُمَسّ حالتُها — تعطيلُ قالبٍ لم يرد
        // لأنّ الرد اقتُطع يوقف تذكيراتٍ سليمة. يُقال العددُ فقط.
        //
        // والمقارنةُ في PHP لا في SQL: تركيبُ المفتاح بـ concat لهجةٌ
        // خاصّة بـ MySQL تسقط على SQLite في الاختبارات، والقوالبُ
        // عشراتٌ لا آلاف.
        $stale = WhatsAppTemplate::query()
            ->get(['name', 'language'])
            ->reject(fn ($row) => in_array($row->name . '|' . $row->language, $seen, true))
            ->count();

        if ($stale > 0) {
            $this->line('  <fg=yellow>' . $stale . ' قالب محفوظ عندنا لم يرد من Meta</> — قد يكون حُذف هناك.');
        }

        if (count($fetched) >= 100) {
            $this->line('  <fg=yellow>ورد مئةُ قالب — قد يكون الرد اقتُطع، وبعضُ القوالب لم يُزامَن.</>');
        }

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * نصُّ مكوّن BODY وحده.
     *
     * القالبُ عند Meta مكوّناتٌ (HEADER · BODY · FOOTER · BUTTONS)،
     * والمتغيّراتُ التي نُرسل قيمَها هي متغيّرات BODY. وأخذُ أوّل مكوّنٍ
     * فيه نصّ كان يلتقط الترويسة أحياناً — فيُحسب عددُ المتغيّرات من
     * الترويسة ويُرسَل عددٌ لا يطابق ما اعتمدته Meta (خطأ 132000).
     */
    private function bodyText(array $entry): ?string
    {
        foreach ((array) ($entry['components'] ?? []) as $component) {
            $component = (array) $component;

            if (strtoupper((string) ($component['type'] ?? '')) === 'BODY') {
                $text = (string) ($component['text'] ?? '');

                return $text !== '' ? $text : null;
            }
        }

        return null;
    }

    /**
     * مواضعُ متغيّرات القالب بترتيبها — «1» ثم «2»…
     *
     * تُقرأ من نصّ BODY لا من أمثلة Meta: الأمثلةُ اختيارية وقد تغيب،
     * والنصُّ هو المصدر الذي تُطابق عليه Meta عدد القيم المرسَلة.
     *
     * والشكلُ هو نفسه الذي يكتبه WhatsAppSettingsController::syncTemplates —
     * قائمةٌ مسطّحة من النصوص. فالعمودُ واحدٌ وله كاتبان، ولو اختلف
     * شكلاهما لانهار كلُّ من يقرأ العمودَ متوقّعاً أحدَهما.
     */
    private function variables(?string $body): array
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function trimTo(mixed $value, int $length): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? null : mb_substr($text, 0, $length);
    }
}
