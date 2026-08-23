<?php

namespace App\Console\Commands;

use App\Support\AiSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * ما النماذج التي يقبلها مفتاح هذا المكتب فعلاً؟
 *
 * سجلّ الإنتاج أظهر: Gemini API error (gemini-2.5-flash): 404. و404
 * تعني أن النموذج غير متاح لهذا المفتاح أو لهذه النسخة من الواجهة —
 * لا أن المفتاح خاطئ. وقائمة النماذج في الإعدادات مكتوبة سلفاً، وهي
 * تتغيّر عند المزوّد بلا إذن منّا.
 *
 * فبدل تخمين اسمٍ آخر ثم تخمين ثالث، نسأل المزوّد نفسه: هذه النماذج
 * التي يقبلها مفتاحك اليوم، وهذه التي يقبل منها generateContent.
 * المفتاح لا يُطبع — يخرج منه آخر أربعة أحرف فقط.
 */
class AiModels extends Command
{
    protected $signature = 'ai:models {--all : اعرض كل النماذج لا التي تصلح للمحادثة فقط}';

    protected $description = 'اسأل مزوّد الذكاء الاصطناعي عن النماذج التي يقبلها مفتاح هذا المكتب';

    public function handle(): int
    {
        $key = AiSettings::apiKey();

        if (!$key) {
            $this->error('لا مفتاح ذكاء اصطناعي في هذا المكتب.');
            $this->line('يُضبط من: الإعدادات ← الذكاء الاصطناعي.');

            return self::FAILURE;
        }

        $this->line('المفتاح المستعمل ينتهي بـ: …' . substr($key, -4));
        $this->line('النموذج المضبوط حالياً: ' . AiSettings::model());
        $this->newLine();

        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-goog-api-key' => $key])
                ->get('https://generativelanguage.googleapis.com/v1beta/models');
        } catch (\Throwable $e) {
            $this->error('تعذّر الوصول إلى المزوّد: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('المزوّد ردّ بـ ' . $response->status());
            $this->line(mb_substr($response->body(), 0, 500));
            $this->newLine();
            $this->line($response->status() === 403 || $response->status() === 400
                ? 'هذا يعني أن المفتاح نفسه مرفوض — جدّده من الإعدادات.'
                : 'هذا ليس خطأ نموذج بل خطأ اتصال أو صلاحية.');

            return self::FAILURE;
        }

        $models = $response->json('models') ?? [];

        if ($models === []) {
            $this->warn('المزوّد لم يُرجع أي نموذج لهذا المفتاح.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($models as $m) {
            $methods = $m['supportedGenerationMethods'] ?? [];
            $chat = in_array('generateContent', $methods, true);

            if (!$chat && !$this->option('all')) {
                continue;
            }

            $rows[] = [
                str_replace('models/', '', $m['name'] ?? '?'),
                $chat ? 'نعم' : 'لا',
                $m['displayName'] ?? '',
            ];
        }

        $this->table(['النموذج', 'يصلح للمحادثة', 'الاسم المعروض'], $rows);
        $this->newLine();
        $this->line('ضع أحد الأسماء التي أمامها «نعم» في: الإعدادات ← الذكاء الاصطناعي ← النموذج.');

        return self::SUCCESS;
    }
}
