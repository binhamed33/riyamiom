<?php

namespace App\Services\WhatsApp;

/**
 * ترجمةُ حمولة Evolution إلى الشكل الذي يفهمه صندوقُ الوارد.
 *
 * ═══ لماذا تُترجَم ولا يُكتب مسارٌ ثانٍ ═══
 *
 * صندوقُ الوارد يعرف شكلاً واحداً — شكلَ Meta. وكتابةُ مسارِ استيعابٍ
 * ثانٍ للجسر تعني قاعدتين لعدم التكرار، ونافذتين، وربطَ موكّلٍ في
 * موضعين — يفترقان بعد شهر، ويقع الخطأُ في أحدهما دون الآخر.
 *
 * فالترجمةُ هنا، والاستيعابُ هناك: InboxService واحدٌ لا يعرف من أين
 * جاءت الرسالة.
 */
class EvolutionPayload
{
    /**
     * أحداثُ الحمولة، كلٌّ بمفتاحٍ لا يتكرّر.
     *
     * @return array<int, array{key: string, kind: string, data: array}>
     */
    public static function events(string $event, array $data): array
    {
        return match (str_replace('.', '_', strtoupper($event))) {
            'MESSAGES_UPSERT' => self::messages($data),
            'MESSAGES_UPDATE' => self::statuses($data),
            default => [],
        };
    }

    /** @return array<int, array{key: string, kind: string, data: array}> */
    private static function messages(array $data): array
    {
        $out = [];

        // الجسر يرسل رسالةً واحدة أحياناً وقائمةً أحياناً
        foreach (self::rows($data) as $row) {
            $key = (array) ($row['key'] ?? []);
            $id = (string) ($key['id'] ?? '');
            $jid = (string) ($key['remoteJid'] ?? '');

            if ($id === '' || $jid === '') {
                continue;
            }

            // ‏fromMe: صدىً لما أرسلناه نحن، أو رسالةٌ كتبها الموظّف من
            // هاتفه. لا تُستوعَب واردةً — وإلا ظهرت رسالةُ المكتب في
            // الخيط كأنّ الموكّل هو من كتبها.
            if (($key['fromMe'] ?? false) === true) {
                continue;
            }

            // المجموعاتُ والبثُّ ليست محادثاتِ موكّلين: خيطٌ لكلّ
            // مجموعةٍ يملأ صندوقَ الوارد بما لا يخصّ المكتب
            if (!str_ends_with($jid, '@s.whatsapp.net')) {
                continue;
            }

            $parsed = self::content((array) ($row['message'] ?? []));

            $out[] = [
                'key' => 'msg:' . $id,
                'kind' => 'message',
                'data' => [
                    'message' => array_filter([
                        'id' => $id,
                        'from' => (string) strtok($jid, '@'),
                        'timestamp' => (string) ($row['messageTimestamp'] ?? time()),
                        'type' => $parsed['type'],
                        $parsed['type'] => $parsed['payload'],
                    ], static fn ($v): bool => $v !== null),
                    'contacts' => [[
                        'wa_id' => (string) strtok($jid, '@'),
                        'profile' => ['name' => (string) ($row['pushName'] ?? '')],
                    ]],
                ],
            ];
        }

        return $out;
    }

    /** @return array<int, array{key: string, kind: string, data: array}> */
    private static function statuses(array $data): array
    {
        $out = [];

        foreach (self::rows($data) as $row) {
            $id = (string) (($row['key']['id'] ?? $row['keyId'] ?? ''));
            $state = self::state((string) ($row['status'] ?? $row['update']['status'] ?? ''));

            if ($id === '' || $state === '') {
                continue;
            }

            $out[] = [
                'key' => 'st:' . $id . ':' . $state,
                'kind' => 'status',
                'data' => ['status' => [
                    'id' => $id,
                    'status' => $state,
                    'timestamp' => (string) time(),
                ]],
            ];
        }

        return $out;
    }

    /** حالاتُ Baileys بأسمائها وأرقامها — إلى مفردات Meta. */
    private static function state(string $raw): string
    {
        return match (strtoupper($raw)) {
            'SERVER_ACK', '2' => 'sent',
            'DELIVERY_ACK', '3' => 'delivered',
            'READ', '4' => 'read',
            'ERROR', '0' => 'failed',
            default => '',
        };
    }

    /**
     * قراءةُ محتوى الرسالة على اختلاف نوعها.
     *
     * ما لا يُفهم يُحفظ نصّاً فارغاً بنوعه: يرى الموظّفُ أنّ شيئاً
     * وصل ويسأل عنه، بدل أن تختفي الرسالة كأنّها لم تكن.
     *
     * @return array{type: string, payload: array<string, mixed>}
     */
    private static function content(array $message): array
    {
        $text = $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? null;

        if (is_string($text) && $text !== '') {
            return ['type' => 'text', 'payload' => ['body' => $text]];
        }

        foreach ([
            'imageMessage' => 'image',
            'videoMessage' => 'video',
            'audioMessage' => 'audio',
            'documentMessage' => 'document',
            'stickerMessage' => 'sticker',
        ] as $node => $type) {
            if (!isset($message[$node])) {
                continue;
            }

            $media = (array) $message[$node];

            return ['type' => $type, 'payload' => array_filter([
                // ‏base64 يصل في الحمولة نفسها حين يُفعّل webhookBase64،
                // ولا مخزنَ عند الجسر يُطلب منه لاحقاً — فيُحمل معه
                'id' => (string) ($media['url'] ?? $media['directPath'] ?? ''),
                'mime_type' => (string) ($media['mimetype'] ?? ''),
                'filename' => isset($media['fileName']) ? (string) $media['fileName'] : null,
                'caption' => isset($media['caption']) ? (string) $media['caption'] : null,
                'sha256' => isset($media['fileLength']) ? null : null,
            ], static fn ($v): bool => $v !== null && $v !== '')];
        }

        return ['type' => 'unsupported', 'payload' => []];
    }

    /** @return array<int, array<string, mixed>> */
    private static function rows(array $data): array
    {
        if (isset($data['key']) || isset($data['message'])) {
            return [$data];
        }

        $rows = $data['messages'] ?? $data;

        return array_values(array_filter(
            (array) $rows,
            static fn ($row): bool => is_array($row),
        ));
    }
}
