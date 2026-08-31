<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\StaffDiscordService;
use App\Support\AttendanceGuard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * سبب الرفض يقوله الخادم صراحةً.
     *
     * كانت الصفحة تخمّن السبب بالبحث عن عبارة في نصّ الصفحة العائدة —
     * ونصّ الصفحة يشمل نصّ الـ script نفسه، والـ script يحمل تلك العبارة
     * في سطر الفحص. فكان كل فشل يظهر «قفل الحساب» ولو كان أول محاولة
     * بكلمة مرور خاطئة.
     *
     * الآن الطلب من الصفحة يُردّ عليه بسبب مُسمّى، والإرسال العادي بلا
     * جافاسكربت يبقى كما كان بالضبط.
     */
    private function reject(Request $request, string $code, string $title, string $detail, string $flash)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => false,
                'code' => $code,
                'title' => $title,
                'detail' => $detail,
            ], 401);
        }

        return redirect()->route('login')->with('login_error', $flash);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->email;
        $lockKey = 'login_lock_' . md5($email);

        if (Cache::has($lockKey)) {
            return $this->reject($request, 'locked',
                'تم تعليق تسجيل الدخول مؤقتًا',
                'محاولات كثيرة خاطئة. حاول بعد ١٥ دقيقة، أو تواصل مع مدير المكتب.',
                'تم قفل الحساب مؤقتاً بسبب محاولات دخول كثيرة. حاول مرة أخرى بعد 15 دقيقة.');
        }

        $attemptsKey = 'login_attempts_' . md5($email);
        $attempts = (int) Cache::get($attemptsKey, 0);

        if (!auth()->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $attempts++;

            if ($attempts >= 5) {
                Cache::put($lockKey, true, now()->addMinutes(15));
                Cache::forget($attemptsKey);

                AuditLog::create([
                    'user_id'    => null,
                    'action'     => 'login_failed_locked',
                    'model_type' => null,
                    'model_id'   => null,
                    'old_values' => null,
                    'new_values' => ['email' => $email, 'attempts' => $attempts],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                $this->sendLockAlert($email, $request->ip(), $request->userAgent());

                return $this->reject($request, 'locked',
                    'تم تعليق تسجيل الدخول مؤقتًا',
                    'محاولات كثيرة خاطئة. حاول بعد ١٥ دقيقة، أو تواصل مع مدير المكتب.',
                    'تم قفل الحساب مؤقتاً بسبب محاولات دخول كثيرة. حاول مرة أخرى بعد 15 دقيقة.');
            }

            Cache::put($attemptsKey, $attempts, now()->addMinutes(15));

            // آخر محاولتين: نقول كم بقي حتى لا يُفاجأ بالقفل
            $left = 5 - $attempts;
            $detail = $left <= 2
                ? 'تحقّق من البريد وكلمة المرور. بقيت لك ' . $left . ' ' . ($left === 1 ? 'محاولة' : 'محاولتان') . ' قبل التعليق المؤقّت.'
                : 'تحقّق من البريد الإلكتروني وكلمة المرور ثم حاول مرة أخرى.';

            return $this->reject($request, 'invalid_credentials',
                'كلمة المرور أو البريد غير صحيح',
                $detail,
                'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
        }

        Cache::forget($attemptsKey);

        if (!auth()->user()->is_active) {
            auth()->logout();

            return $this->reject($request, 'disabled',
                'حسابك معطَّل',
                'تواصل مع مدير المكتب لإعادة تفعيله.',
                'تم تعطيل حسابك. تواصل مع مدير المكتب.');
        }

        $request->session()->regenerate();

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => AuditLog::ACTION_LOGIN,
            'model_type' => null,
            'model_id'   => null,
            'old_values' => null,
            'new_values' => ['email' => $email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        app(StaffDiscordService::class)->reportLogin(
            auth()->user(),
            $request->ip(),
            $request->userAgent()
        );

        // الحضور يُسجَّل هنا لا في وسيط: الوسيط يمرّ على كل طلب فيعيد
        // المحاولة مئة مرة في اليوم، والدخول يقع مرة. والفشل لا يمنع
        // الدخول — AttendanceGuard يبتلع عطله ويُرجع null.
        $attendance = AttendanceGuard::checkInOnLogin(auth()->user());

        if ($attendance) {
            $request->session()->put('attendance_flash', [
                'created' => $attendance['created'],
                'at' => $attendance['record']->check_in_at->timezone('Asia/Muscat')->format('h:i A'),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function sendLockAlert(string $email, string $ip, ?string $userAgent): void
    {
        $webhook = config('services.discord.log_webhook');
        if (!$webhook) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, [
                'content' => null,
                'embeds' => [[
                    'title' => '🔒 تم قفل حساب',
                    'color' => 0xE74C3C,
                    'fields' => [
                        ['name' => 'البريد', 'value' => $email, 'inline' => true],
                        ['name' => 'IP', 'value' => $ip, 'inline' => true],
                        ['name' => 'الجهاز', 'value' => $userAgent ?? 'غير معروف', 'inline' => false],
                        ['name' => 'الوقت', 'value' => now()->format('Y-m-d H:i:s'), 'inline' => true],
                    ],
                    'footer' => ['text' => 'مُداوَلة - نظام الإنذار'],
                    'timestamp' => now()->toIso8601String(),
                ]],
            ]);
        } catch (\Exception $e) {
            Log::warning('Discord webhook failed', ['error' => $e->getMessage()]);
        }
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        $userId = auth()->id();
        $name = $user?->name;
        $email = $user?->email;
        $ip = $request->ip();

        app(StaffDiscordService::class)->reportLogout($user, $ip);

        // قبل إبطال الجلسة: بعدها لا يبقى مستخدمٌ نَنسب إليه الانصراف.
        //
        // وخروجُ الخمول التلقائي (auto=1) ليس «زرَّ الخروج»: تسجيلُ
        // انصرافٍ عنده يعيد الشكوى التي أُغلق بابها — محامٍ انشغل عن
        // الشاشة إحدى عشرة دقيقةً وُجد منصرفاً ودوامُه قائم. الجلسة
        // تُغلق للأمان، والانصرافُ للزرّ الصريح وحده.
        if (! $request->boolean('auto')) {
            AttendanceGuard::checkOutOnLogout($user);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::create([
            'user_id'    => $userId,
            'action'     => AuditLog::ACTION_LOGOUT,
            'model_type' => null,
            'model_id'   => null,
            'old_values' => null,
            'new_values' => ['email' => $email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);


        return redirect()->route('login');
    }
}
