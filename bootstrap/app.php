<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'active' => \App\Http\Middleware\CheckActiveUser::class,
            'feature' => \App\Http\Middleware\CheckFeatureAccess::class,
            'subscription' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'client.portal' => \App\Http\Middleware\ClientPortalGuard::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\PreventBrowserCache::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\TrackUserActivity::class);
        $middleware->appendToGroup('web', \Illuminate\Http\Middleware\HandleCors::class);
        $middleware->replaceInGroup('web', \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \App\Http\Middleware\VerifyCsrfToken::class);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $e) {
            logger()->error($e->getMessage(), ['exception' => $e]);
        });

        $exceptions->dontReport(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $exceptions->dontReport(\Illuminate\Auth\AuthenticationException::class);

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Server Error'], 500);
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Validation\ValidationException) {
                return;
            }
            // رفض صلاحية: رسالة تقول السبب بدل «حدث خطأ أثناء تنفيذ العملية»
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() === 403) {
                $message = $e->getMessage() ?: 'ليس لديك صلاحية للوصول إلى هذه الصفحة.';

                return auth()->check()
                    ? redirect()->route('dashboard')->with('error', $message)
                    : redirect()->route('login')->with('login_error', $message);
            }

            // تجاوز حد المحاولات لزائر غير مسجّل: يعود لصفحة الدخول برسالة واضحة
            // بدل إرساله إلى لوحة التحكم حيث لا يرى التنبيه أصلاً
            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException && !auth()->check()) {
                return redirect()->route('login')
                    ->with('login_error', 'محاولات كثيرة خلال وقت قصير. انتظر دقيقة ثم أعد المحاولة.');
            }

            $route = $request->route();
            if ($route && $route->getName() === 'dashboard') {
                logger()->error('Dashboard render failed, returning 500 to avoid redirect loop: ' . $e->getMessage(), ['exception' => $e]);

                return response('تعذر تحميل لوحة التحكم، يرجى مراجعة السجلات.', 500);
            }
            return redirect()->route('dashboard')
                ->with('error', 'حدث خطأ أثناء تنفيذ العملية: ' . $e->getMessage());
        });
    })->create();
