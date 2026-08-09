<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\StaffDiscordService;

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

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->email;
        $lockKey = 'login_lock_' . md5($email);

        if (Cache::has($lockKey)) {
            return redirect()->route('login')->with('login_error', 'تم قفل الحساب مؤقتاً بسبب محاولات دخول كثيرة. حاول مرة أخرى بعد 15 دقيقة.');
        }

        $attemptsKey = 'login_attempts_' . md5($email);
        $attempts = (int) Cache::get($attemptsKey, 0);

        if (!auth()->attempt($request->only('email', 'password'))) {
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

                return redirect()->route('login')->with('login_error', 'تم قفل الحساب مؤقتاً بسبب محاولات دخول كثيرة. حاول مرة أخرى بعد 15 دقيقة.');
            }

            Cache::put($attemptsKey, $attempts, now()->addMinutes(15));

            return redirect()->route('login')->with('login_error', 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
        }

        Cache::forget($attemptsKey);

        if (!auth()->user()->is_active) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'تم تعطيل حسابك');
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
                    'footer' => ['text' => 'LexPro - نظام الإنذار'],
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
