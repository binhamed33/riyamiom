<?php

namespace App\Console\Commands;

use App\Jobs\SendWhatsAppMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * إفراجُ الرسائل المحجوزة التي حان موعدُها — كلَّ دقيقة.
 *
 * منفصلٌ عن الكنس (whatsapp:sweep) لأنّ الكنسَ ثقيل: يسأل الجسرَ عن الاقتران
 * ويقلّم الدفاتر، فلا يُشغَّل كلَّ دقيقة. وهذا استعلامٌ واحدٌ رخيص.
 */
class WhatsAppReleaseHeld extends Command
{
    protected $signature = 'whatsapp:release-held';

    protected $description = 'دفعُ رسائل واتساب المحجوزة التي حان موعدها';

    public function handle(): int
    {
        if (!Schema::hasColumn('whatsapp_messages', 'hold_until')) {
            return self::SUCCESS;
        }

        $released = SendWhatsAppMessage::releaseDue();

        if ($released > 0) {
            $this->line('أُفرج عن ' . $released . ' رسالة حان موعدها');
        }

        return self::SUCCESS;
    }
}
