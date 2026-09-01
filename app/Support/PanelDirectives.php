<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * تطبيقُ أوامر اللوحة — المكتبُ يطبّق بيده ويقرّ بما فعل.
 *
 * ═══ حدود الثقة ═══
 *
 * الأمرُ قادمٌ من لوحة مُداوَلة عبر قناةٍ برمزٍ خاص، ومع ذلك يُعامل
 * ككلّ مدخل: نوعٌ من قاموسٍ مغلق، ورتبةٌ من قائمةٍ مسمّاة، وبريدٌ
 * يُبحث عنه كما هو. أمرٌ لا يُفهم يُرَدّ «تعذّر» بسببه — لا يُخمَّن.
 *
 * وكلُّ تطبيقٍ يُسجَّل في سجلّ المكتب: من غُيّرت رتبتُه يظهر أثرُه
 * عند المكتب نفسه لا في اللوحة وحدها.
 */
class PanelDirectives
{
    /** الرتبُ التي تُقبل من اللوحة — لا employee في هذا النظام. */
    private const ROLES = ['developer', 'admin', 'lawyer', 'staff'];

    /**
     * @param array{id?: int, type?: string, payload?: array<string, mixed>} $directive
     * @return array{ok: bool, message: string}
     */
    public static function apply(array $directive): array
    {
        $type = (string) ($directive['type'] ?? '');
        $payload = is_array($directive['payload'] ?? null) ? $directive['payload'] : [];

        $result = match ($type) {
            'set_user_role' => self::setUserRole($payload),
            default => ['ok' => false, 'message' => 'نوع أمرٍ غير معروف: ' . mb_substr($type, 0, 40)],
        };

        Log::info('Panel directive ' . ($result['ok'] ? 'applied' : 'refused'), [
            'type' => $type,
            'message' => $result['message'],
        ]);

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private static function setUserRole(array $payload): array
    {
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $role = (string) ($payload['role'] ?? '');

        if ($email === '' || !in_array($role, self::ROLES, true)) {
            return ['ok' => false, 'message' => 'بريدٌ أو رتبةٌ غير صالحة'];
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$user) {
            return ['ok' => false, 'message' => 'لا مستخدم بهذا البريد في المكتب'];
        }

        if ($user->role === $role) {
            return ['ok' => true, 'message' => 'الرتبة هي نفسُها أصلاً (' . $role . ')'];
        }

        $old = (string) $user->role;
        $user->forceFill(['role' => $role])->save();

        return ['ok' => true, 'message' => 'صارت رتبة ' . ($user->name ?: $email) . ': ' . $role . ' (كانت ' . $old . ')'];
    }
}
