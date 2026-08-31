<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * دفترُ أحداث الويبهوك — «رأيتُ هذا الحدث» قبل معالجته.
 *
 * ═══ لماذا يُدوَّن الحدث قبل أن يُعالَج ═══
 *
 * تُعيد Meta إرسال الإشعار إن لم تتلقَّ 200 سريعاً، وتُعيده أيضاً بلا
 * سببٍ أحياناً. فبلا دفترٍ يُقيَّد فيه المفتاح أوّلاً تُنشأ الرسالةُ
 * مرّتين في الخيط، ويُنفَّذ ردُّ الذكاء الاصطناعي مرّتين، وتُخصم
 * رسالتان من حساب المكتب.
 *
 * والقيدُ على قاعدة البيانات (unique) لا في الكود: عاملان متزامنان
 * يقرآن «غير موجود» في اللحظة نفسها، ولا يفلت منهما إلا قيدٌ ذرّي.
 */
class WhatsAppWebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'event_key',
        'kind',
        'payload',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function markProcessed(): void
    {
        $this->forceFill(['processed_at' => now(), 'error' => null])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill(['error' => mb_substr($reason, 0, 500)])->save();
    }
}
