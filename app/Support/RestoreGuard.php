<?php

namespace App\Support;

use App\Models\User;

/**
 * حارسُ استعادة النسخة الاحتياطية.
 *
 * ═══ الثغرة ═══
 *
 * الاستعادةُ تصبّ ملفَّ SQL في قاعدة المكتب كما هو. ومن يملك زرَّها
 * يملك — بملف zip واحدٍ يكتبه بيده — أن يرفع نفسَه مطوّراً
 * (UPDATE users SET role='developer')، أو يغيّر أيَّ صفٍّ، أو يزرع
 * أمراً لا تحويه نسخةٌ احتياطيةٌ قطّ.
 *
 * ═══ طبقتان لا واحدة ═══
 *
 * ١) فحصُ الملفّ قبل الصبّ: نسخةُ mysqldump لها بصمةٌ معروفة، ولا
 *    تحوي CREATE USER ولا GRANT ولا LOAD DATA. وجودُ واحدٍ منها يعني
 *    أنّ الملفَّ لم يخرج من النظام. وهذا ليس الحارسَ الوحيد لأنّ
 *    تمويهَ نصٍّ ممكنٌ دائماً — لكنّه يُسقط الهجومَ الساذج ويسمّيه.
 *
 * ٢) إعادةُ تثبيت الأدوار بعد الصبّ: تُلتقط أدوارُ المستخدمين قبله،
 *    وكلُّ من صار «مطوّراً» ولم يكن يعود إلى ما كان. وهذا ما لا
 *    يُتحايَل عليه: مهما كُتب في الملفّ، الدورُ يعود.
 *
 * والمطوّرُ الحقيقيُّ (من كان مطوّراً قبل الاستعادة) لا يُمسّ.
 */
class RestoreGuard
{
    /** بصماتُ تصديرٍ حقيقيّ — واحدةٌ منها في أوّل ألفي حرف */
    private const DUMP_SIGNATURES = ['-- MySQL dump', '-- MariaDB dump', '/*!40101 SET', '/*!40103 SET', 'CREATE TABLE'];

    /**
     * أوامرُ لا تحويها نسخةٌ احتياطيةٌ قطّ.
     *
     * @var array<string,string> النمط ⇐ الاسمُ الذي يُقال للمستخدم
     */
    private const FORBIDDEN = [
        '/\bCREATE\s+USER\b/i' => 'CREATE USER',
        '/\bALTER\s+USER\b/i' => 'ALTER USER',
        '/\bDROP\s+USER\b/i' => 'DROP USER',
        '/\bGRANT\b/i' => 'GRANT',
        '/\bREVOKE\b/i' => 'REVOKE',
        '/\bSET\s+GLOBAL\b/i' => 'SET GLOBAL',
        '/\bSET\s+PERSIST\b/i' => 'SET PERSIST',
        '/\bLOAD\s+DATA\b/i' => 'LOAD DATA',
        '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i' => 'INTO OUTFILE',
        '/\bLOAD_FILE\s*\(/i' => 'LOAD_FILE',
        /*
         * ═══ ‏TRIGGER كان ينقض الحارسَ الذي بعده ═══
         *
         * ‏reassertRoles تعيد الدورَ بعد الاستعادة — و
         * ‏«CREATE TRIGGER … BEFORE UPDATE ON users SET NEW.role='developer'»
         * يجعل تلك الإعادةَ نفسَها تكتب «developer» من جديد. أي أنّ
         * الملفَّ يُبطل الحارسَ الذي كُتب ليردّه.
         * وVIEW مثلُها: تُبدَّل «users» بمنظورٍ يخفي أو يزوّر.
         */
        '/\bCREATE\s+(OR\s+REPLACE\s+)?(DEFINER\s*=\s*\S+\s+)?(SQL\s+SECURITY\s+\w+\s+)?(PROCEDURE|FUNCTION|EVENT|TRIGGER|VIEW)\b/i' => 'CREATE PROCEDURE/FUNCTION/EVENT/TRIGGER/VIEW',
        '/\bDEFINER\s*=/i' => 'DEFINER',
        '/\bDROP\s+DATABASE\b/i' => 'DROP DATABASE',
        '/\bRENAME\s+TABLE\b/i' => 'RENAME TABLE',
        '/\bINSTALL\s+(PLUGIN|COMPONENT)\b/i' => 'INSTALL PLUGIN',
        '/^\s*(system|\\\\!)\s/im' => 'system',
        // تصديرُ النظام يخرج بلا USE (mysqldump على قاعدةٍ واحدة بلا
        // --databases). ووجودُه يعني انتقالاً إلى قاعدةٍ لم تُطلب.
        '/^\s*USE\s+/im' => 'USE',
    ];

    /**
     * يفحص نصَّ SQL ويعيد سببَ الرفض — أو null إن مرّ.
     */
    public static function inspect(string $sql): ?string
    {
        $head = substr($sql, 0, 2048);

        $signed = false;
        foreach (self::DUMP_SIGNATURES as $signature) {
            if (str_contains($head, $signature)) {
                $signed = true;
                break;
            }
        }

        if (!$signed) {
            return 'الملفُّ ليس تصديرَ قاعدةِ بياناتٍ من النظام (لا بصمةَ mysqldump في أوّله).';
        }

        // التعليقاتُ تُنزع أوّلاً: أمرٌ مموَّهٌ داخل /* */ يمرّ على القراءة
        // ويُنفَّذ عند الخادم في بعض الصيغ (/*! ... */) — فلا تُصدَّق
        $bare = preg_replace('~/\*(?!!).*?\*/~s', ' ', $sql) ?? $sql;
        $bare = preg_replace('/^\s*--.*$/m', ' ', $bare) ?? $bare;

        foreach (self::FORBIDDEN as $pattern => $name) {
            if (preg_match($pattern, $bare) === 1) {
                return "الملفُّ يحوي أمراً لا تحويه نسخةٌ احتياطية ({$name}) — رُفض.";
            }
        }

        return null;
    }

    /**
     * أدوارُ المستخدمين الآن — تُلتقط قبل الصبّ.
     *
     * @return array<int,string> المعرّف ⇐ الدور
     */
    public static function snapshotRoles(): array
    {
        return User::query()->pluck('role', 'id')->map(fn ($r) => (string) $r)->all();
    }

    /**
     * بعد الصبّ: مَن صار مطوّراً ولم يكن يعود إلى ما كان.
     *
     * والصفُّ الجديدُ كلّياً (لم يكن في اللقطة) لا يُمنح «مطوّر» أبداً:
     * يُنزَّل إلى «مدير» — المكتبُ لا يصنع مطوّرين، اللوحةُ تصنعهم.
     *
     * @param  array<int,string>  $before
     * @return int عددُ الأدوار التي أُعيدت
     */
    public static function reassertRoles(array $before): int
    {
        $fixed = 0;

        foreach (User::query()->where('role', 'developer')->get(['id', 'role']) as $user) {
            $was = $before[$user->id] ?? null;

            if ($was === 'developer') {
                continue;
            }

            User::query()->whereKey($user->id)->update(['role' => $was ?? 'admin']);
            $fixed++;
        }

        return $fixed;
    }

    /**
     * اعتمادُ حسابات المطوّرين — لا الدورُ وحده.
     *
     * ═══ الثغرةُ التي تركها حارسُ الأدوار مفتوحة ═══
     *
     * ‏reassertRoles تفحص من صار «developer» ولم يكن. وهي لا تنظر إلى
     * من كان مطوّراً وبقي — فسطرٌ في الملفّ:
     *
     *     UPDATE users SET email='me@evil.tld',
     *            password='<تجزئةُ المهاجم>' WHERE role='developer';
     *
     * يترك الدورَ كما هو تماماً، فلا تلمسه reassertRoles، ويصير
     * حسابُ المطوّر بيد رافع النسخة. ووعدُ الحارس المكتوب — «مهما
     * كُتب في الملفّ، الدورُ يعود» — يصدق حرفياً ويخيب معنى.
     *
     * فتُلتقط تجزئةُ الكلمة والبريدُ ورمزُ التذكّر قبل الاستعادة،
     * ويُعادون بعدها. ومن غيّرها في الملفّ لم يغيّر شيئاً.
     *
     * @return array<int, array{email: string, password: string, remember_token: ?string}>
     */
    public static function snapshotDeveloperCredentials(): array
    {
        return User::query()
            ->where('role', 'developer')
            ->get(['id', 'email', 'password', 'remember_token'])
            ->mapWithKeys(fn ($u) => [$u->id => [
                'email' => $u->email,
                'password' => $u->password,
                'remember_token' => $u->remember_token,
            ]])
            ->all();
    }

    /**
     * @param  array<int, array{email: string, password: string, remember_token: ?string}>  $before
     * @return int عددُ الحسابات التي أُعيد اعتمادُها
     */
    public static function reassertDeveloperCredentials(array $before): int
    {
        $fixed = 0;

        foreach ($before as $id => $creds) {
            $now = User::query()->whereKey($id)->first(['id', 'email', 'password']);

            if (!$now) {
                continue;
            }

            if ($now->email === $creds['email'] && $now->password === $creds['password']) {
                continue;
            }

            User::query()->whereKey($id)->update([
                'email' => $creds['email'],
                'password' => $creds['password'],
                'remember_token' => $creds['remember_token'],
            ]);
            $fixed++;
        }

        return $fixed;
    }
}
