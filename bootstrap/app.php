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
            $route = $request->route();
            if ($route && $route->getName() === 'dashboard') {
                logger()->error('Dashboard render failed, returning 500 to avoid redirect loop: ' . $e->getMessage(), ['exception' => $e]);

                return response('تعذر تحميل لوحة التحكم، يرجى مراجعة السجلات.', 500);
            }
            return redirect()->route('dashboard')
                ->with('error', 'حدث خطأ أثناء تنفيذ العملية: ' . $e->getMessage());
        });
    })->create();
