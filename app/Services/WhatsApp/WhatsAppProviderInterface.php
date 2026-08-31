<?php

namespace App\Services\WhatsApp;

/**
 * عقدُ مزوّد واتساب.
 *
 * ═══ لماذا واجهة لمزوّدٍ واحد ═══
 *
 * المتحكّمات والمهامّ لا تعرف Meta ولا عنوانَ Graph ولا شكلَ ردّه —
 * تعرف هذا العقد وحده. فيوم يُضاف مزوّدٌ آخر (أو تُغيّر Meta شكل
 * واجهتها كما فعلت مراراً) يُكتب صنفٌ جديد ولا تُمَسّ شاشةُ المحادثات
 * ولا المهامّ ولا الجدولة.
 *
 * وهو نفس النمط المتّبع في مزوّدي الذكاء الاصطناعي في هذا المشروع.
 */
interface WhatsAppProviderInterface
{
    /** هل بيانات الاعتماد مكتملة أصلاً؟ لا يُستدعى شيءٌ آخر قبلها. */
    public function isConfigured(): bool;

    /** ردٌّ حرّ — لا يجوز إلا داخل نافذة الأربع والعشرين ساعة. */
    public function sendText(string $to, string $body): SendResult;

    /**
     * قالبٌ معتمَد — الطريق الوحيد لبدء محادثةٍ من طرف المكتب.
     *
     * @param array<int, string> $bodyParams قيم المتغيّرات بترتيبها
     */
    public function sendTemplate(string $to, string $name, string $language, array $bodyParams = []): SendResult;

    /**
     * وسائط برفعها أوّلاً إلى Meta ثم إرسال معرّفها.
     *
     * @param string $type image|document|audio|video
     */
    public function sendMedia(
        string $to,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
    ): SendResult;

    /** رفعُ ملفٍّ من القرص وإرجاعُ معرّفه عند Meta. */
    public function uploadMedia(string $absolutePath, string $mime): ?string;

    /** عنوانُ تنزيلٍ مؤقّت لوسيطٍ وارد + بياناته (mime, sha256, file_size). */
    public function mediaMeta(string $mediaId): ?array;

    /** التنزيل الفعلي — يحتاج الرمز في الترويسة حتى مع العنوان المؤقّت. */
    public function downloadMedia(string $url): ?string;

    /** تعليم رسالةٍ واردة كمقروءة عند العميل. */
    public function markRead(string $wamid): bool;

    /** فحصُ اتصالٍ حقيقي — يعيد ['ok' => bool, 'message' => string, ...]. */
    public function testConnection(): array;

    /** قوالبُ الحساب وحالتُها عند Meta — لا نعتمدها نحن. */
    public function fetchTemplates(): array;

    /**
     * تسجيلُ عنوان الويبهوك عند المزوّد — بدل أن يفتح المكتبُ لوحته.
     *
     * @param array<int, string> $fields
     */
    public function registerWebhook(string $callbackUrl, string $verifyToken, array $fields): bool;

    /** اشتراكُ تطبيقنا في حساب هذا المكتب — لا يكفي تسجيلُ العنوان. */
    public function subscribeAccount(): bool;

    /**
     * حقولُ الويبهوك المشترَك فيها فعلاً — أو null إن تعذّر السؤال.
     *
     * وnull تختلف عن `[]`: الأولى «لم أستطع أن أسأل»، والثانية
     * «سألتُ والجواب: لا اشتراك». والمعالجُ يقول للمكتب أحدَهما.
     *
     * @return array<int, string>|null
     */
    public function subscribedFields(): ?array;

    /**
     * ما يُستنتج من الرمز نفسه: معرّفُ الحساب والرقم — بدل نسخِهما.
     *
     * @return array{waba_id: ?string, phone_number_id: ?string, display_phone: ?string, choices: array<int, string>}
     */
    public function discover(): array;

    /** آخرُ خطأٍ بصيغةٍ مفهومة — بلا رموز سرّية. */
    public function getLastError(): ?string;
}
