<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        // SAMEORIGIN لا DENY: عارض المستندات يعرض PDF داخل إطار من
        // الموقع نفسه. DENY كانت تحجب الموقع عن نفسه فتظهر المعاينة
        // صندوقاً فارغاً — والحماية من مواقع خارجية تبقى كاملة.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        $csp = [
            "default-src 'self'",
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https: http:",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.tailwindcss.com",
            "img-src 'self' data: blob: https://static.cloudflareinsights.com",
            "connect-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://static.cloudflareinsights.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        // ═══ سياسةٌ وضعها المتحكّمُ لا تُستبدل ═══
        //
        // Attachments::respond تُقدّم المرفقَ بـ"default-src 'none'; sandbox"
        // — عزلٌ مقصودٌ لملفٍّ رفعه غيرُ صاحب الصفحة. وهذا الوسيطُ يعمل
        // بعد المتحكّم، فكان set() يستبدل ذلك العزلَ بسياسة التطبيق
        // السخيّة في كلّ مرفق. والحارسُ نفسُه يستعمله لارافل في
        // ServeFile: ما وُضع لا يُداس.
        if (!$response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        }

        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
