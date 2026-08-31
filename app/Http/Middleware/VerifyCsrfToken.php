<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    protected $except = [
        'logout',

        // ويبهوك واتساب: Meta تنادي من خوادمها بلا جلسةٍ ولا رمز CSRF.
        // الاستثناء ليس ثغرة — البديلُ عن رمز الجلسة هنا توقيعُ
        // HMAC-SHA256 على الجسم الخام بسرّ تطبيق هذا المكتب، ويُرفض
        // الطلبُ كلُّه بلا توقيعٍ صحيح. راجع WhatsAppWebhookController.
        'webhooks/whatsapp',
    ];

    protected function tokensMatch($request)
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return false;
        }

        $sessionToken = $request->session()->token();

        if ($sessionToken && hash_equals($sessionToken, $token)) {
            return true;
        }

        $cookieToken = $request->cookie('XSRF-TOKEN');

        if ($cookieToken && hash_equals($cookieToken, $token)) {
            return true;
        }

        return false;
    }
}
