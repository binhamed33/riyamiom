<?php

namespace App\Console\Commands;

use App\Services\PanelReporter;
use Illuminate\Console\Command;

/**
 * نبضة إلى لوحة مُداوَلة.
 * تخرج فوراً إن لم يكن هذا المكتب مربوطاً باللوحة — مكتب مستقل لا
 * يتصل بشيء ولا يتأخر بسببها.
 */
class PanelHeartbeat extends Command
{
    protected $signature = 'panel:heartbeat {--strict : أرجِع خطأً إن لم تصل النبضة — لمن يقرأ رمز الخروج}';

    protected $description = 'إبلاغ لوحة مُداوَلة بحالة هذا المكتب (إن كان مربوطاً بها)';

    public function handle(): int
    {
        if (!PanelReporter::configured()) {
            $this->line('غير مربوط بلوحة — لا شيء يُرسل.');

            // «لا شيء يُرسل» ليست نجاحاً حين يسأل أحدٌ «أوصلت النبضة؟»
            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $ok = PanelReporter::heartbeat();
        $this->line($ok ? 'وصلت النبضة.' : 'تعذّر إرسال النبضة — سيُعاد في الموعد التالي.');

        // ═══ لماذا رمزان لا رمزٌ واحد ═══
        //
        // فشلُ الإبلاغ ليس فشلَ المكتب، فالمجدولُ لا يُقلَق به: يُعاد
        // في الموعد التالي وكفى. ولذلك كان الأمر يُرجع النجاح دائماً.
        //
        // لكنّ update-all.sh كان يقرأ هذا الرمز نفسه ويطبع
        // «✓ نبضة إلى اللوحة — الحدود والباقة مُزامَنة». فطُبع السطر
        // لمكاتب غير مربوطة أصلاً، سطراً بعد «المكتب غير مربوط بلوحة
        // مُداوَلة» مباشرةً — تناقضٌ في مخرَجٍ واحد.
        //
        // فالافتراض كما كان لأجل المجدول، و‎--strict لمن يبني على الرمز
        // حكماً يعرضه على إنسان.
        if ($this->option('strict')) {
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
