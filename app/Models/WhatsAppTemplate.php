<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * قالبُ واتساب كما تعرفه Meta.
 *
 * ═══ لماذا لا نعتمد القوالب نحن ═══
 *
 * الاعتماد قرارُ Meta وحدها، وقد يستغرق ساعاتٍ وقد يُرفض. فلو عرضنا
 * قالباً «جاهزاً» بمجرّد كتابته لأرسل المكتبُ به فيُرفض عند أوّل
 * استعمال بخطأ 132001 — ويرى المحامي «فشل الإرسال» بلا سبب. الحالة
 * هنا مرآةٌ لما عند Meta لا حكمٌ منّا.
 */
class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    public const APPROVED = 'APPROVED';

    protected $fillable = [
        'name',
        'language',
        'category',
        'status',
        'body',
        'variables',
        'meta_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return strtoupper((string) $this->status) === self::APPROVED;
    }

    /** الحالة بالعربية — الواجهة لا تعرض كلمة Meta الإنجليزية. */
    public function statusLabel(): string
    {
        return match (strtoupper((string) $this->status)) {
            'APPROVED' => 'معتمَد',
            'PENDING', 'PENDING_DELETION' => 'قيد المراجعة',
            'REJECTED' => 'مرفوض',
            'PAUSED' => 'موقوف',
            'DISABLED' => 'معطَّل',
            default => 'غير معروف',
        };
    }

    public function statusBadge(): string
    {
        return match (strtoupper((string) $this->status)) {
            'APPROVED' => 'bg-green-50 text-green-700 border-green-200',
            'PENDING', 'PENDING_DELETION' => 'bg-amber-50 text-amber-700 border-amber-200',
            'REJECTED', 'DISABLED' => 'bg-red-50 text-red-700 border-red-200',
            default => 'bg-gray-50 text-gray-600 border-gray-200',
        };
    }

    /** كم متغيّراً يتوقّعه هذا القالب — لتحقّق عدد القيم قبل الإرسال. */
    public function variableCount(): int
    {
        if (is_array($this->variables) && $this->variables !== []) {
            return count($this->variables);
        }

        preg_match_all('/\{\{\s*\d+\s*\}\}/', (string) $this->body, $matches);

        return count($matches[0] ?? []);
    }
}
