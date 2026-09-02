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
            'wa.inbox' => \App\Http\Middleware\WhatsAppInboxGuard::class,
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
        // ═══ لا يدخل السجلَّ سرٌّ ═══
        //
        // رسالةُ TransportException من Symfony تحمل اسمَ المستخدم نصّاً:
        // «Failed to authenticate on SMTP server with username "…"».
        // وsetPassword موسومةٌ #[SensitiveParameter] فتبقى كلمةُ المرور
        // خارج الأثر، أمّا setUsername فلا — فهو ما يتسرّب.
        //
        // وكان OfficeMailer وOfficeMail ينقّيان ما يدوّنانه، ثم يأتي
        // هذا المُبلِّغ العام فيكتب الرسالة خاماً مرّةً أخرى. وسجلُّ
        // المكتب يُقرأ ويُنسخ ويُرسَل عند الشكوى.
        //
        // ويعود null لا false عمداً: false يُوقف مُبلِّغ لارافل
        // الافتراضي، وهو من يكتب الأثر الكامل الذي نحتاجه للتشخيص.
        // فالمكتوب هنا منقّىً، والافتراضيُّ يكتب ما يكتب.
        $exceptions->report(function (\Throwable $e) {
            logger()->error(\App\Support\MailIdentity::scrub($e->getMessage()), ['exception' => $e]);
        });

        $exceptions->dontReport(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $exceptions->dontReport(\Illuminate\Auth\AuthenticationException::class);
        // بوتات الإنترنت ترمي POST على الصفحة الرئيسة طوال اليوم — طلب
        // مرفوض من عميل غريب ليس عطلاً في النظام، وتسجيله ERROR كان
        // يجعل كل مكتب يظهر في اللوحة وكأن فيه أخطاء يومية.
        $exceptions->dontReport(\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class);

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // رمز الحالة الحقيقي لا 500 دائماً: منعُ صلاحية ليس عطلاً في
                // الخادم، وطلبٌ لصفحة غير موجودة ليس عطلاً كذلك. إرجاع 500
                // لكل شيء يُضلّل من يستدعي النقطة ويُخفي السبب الحقيقي.
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return;   // Laravel يردّ 422 بتفاصيل الحقول
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();

                    return response()->json([
                        'ok' => false,
                        'error' => match (true) {
                            $status === 403 => $e->getMessage() ?: 'غير مصرح لك بالوصول',
                            $status === 404 => 'غير موجود',
                            $status === 429 => 'محاولات كثيرة خلال وقت قصير',
                            default => $e->getMessage() ?: 'تعذّر إتمام العملية',
                        },
                    ], $status);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json(['ok' => false, 'error' => 'الجلسة منتهية'], 401);
                }

                return response()->json(['ok' => false, 'error' => 'تعذّر إتمام العملية'], 500);
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Validation\ValidationException) {
                return;
            }
            // ═══ «419 PAGE EXPIRED» شاشةٌ بلا مخرج ═══
            //
            // صفحةٌ تُترك مفتوحةً ساعاتٍ ثمّ يُضغط زرُّها، فيكون رمزُ
            // الحماية قد انتهى مع الجلسة. والردُّ الافتراضيّ صفحةٌ
            // سوداءُ فيها رقمٌ وكلمتان بالإنجليزية — لا سببَ ولا رجوع،
            // فيظنّ المحامي أنّ النظام سقط ويتصل بالدعم.
            //
            // ولا يُلتقَط بنوعه: لارافل يحوّل TokenMismatchException إلى
            // HttpException(419) قبل أن يسأل معالجاتِنا. فيُلتقَط برمزه.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getStatusCode() === 419) {
                $message = 'انتهت صلاحية الصفحة لطول بقائها مفتوحة — أعد المحاولة الآن.';

                // كلمةُ المرور لا تُعاد إلى النموذج ولا تُحفظ في الجلسة
                return auth()->check()
                    ? redirect()->to($request->fullUrl())
                        ->withInput($request->except(['password', 'password_confirmation', '_token']))
                        ->with('error', $message)
                    : redirect()->route('login')->with('login_error', $message);
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
            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                if (!auth()->check()) {
                    return redirect()->route('login')
                        ->with('login_error', 'محاولات كثيرة خلال وقت قصير. انتظر دقيقة ثم أعد المحاولة.');
                }

                // مستخدم مسجَّل تجاوز حدّ نموذج ما: يعود لصفحته برسالة
                // تقول السبب. كانت تسقط في المعالج العام فتخرج «حدث خطأ
                // أثناء تنفيذ العملية: Too Many Attempts» — رسالة تُقلق
                // ولا تُفهم، وتُرسله إلى لوحة التحكم بعيداً عن نموذجه.
                $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $minutes = max(1, (int) ceil($seconds / 60));

                return back()->with('error', "أرسلت عدة طلبات متتالية. انتظر {$minutes} دقيقة ثم أعد المحاولة.");
            }

            $route = $request->route();
            if ($route && $route->getName() === 'dashboard') {
                logger()->error('Dashboard render failed, returning 500 to avoid redirect loop: ' . $e->getMessage(), ['exception' => $e]);

                return response('تعذر تحميل لوحة التحكم، يرجى مراجعة السجلات.', 500);
            }
            // تفاصيل الاستثناء للسجلّ لا للمستخدم: رسائل SQL وأسماء
            // الأصناف لا تعني له شيئاً، وقد تكشف بنية النظام. ويُعطى
            // مرجعاً يُربط به السجلّ عند الشكوى.
            $reference = strtoupper(substr(md5($e->getMessage() . microtime()), 0, 8));

            // طلب بطريقة غير مدعومة (بوت يرمي POST على /) أو صفحة غير
            // موجودة: ضجيج عملاء لا عطل نظام — يُدوَّن INFO كي لا يعدّه
            // نبضُ الأخطاء ولا يوقظ فحصَ الصحة اليومي زوراً.
            $isClientNoise = $e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

            if ($isClientNoise) {
                logger()->info('Client request refused [' . $reference . ']: ' . $e->getMessage(), [
                    'url' => $request->fullUrl(),
                ]);
            } else {
                logger()->error('Unhandled exception [' . $reference . ']: ' . $e->getMessage(), [
                    'exception' => $e,
                    'url' => $request->fullUrl(),
                    'user_id' => auth()->id(),
                ]);
            }

            $message = 'تعذّر إتمام العملية. أعد المحاولة، وإن تكرّر أبلغ الدعم بالرمز: ' . $reference;

            // إرسال نموذج: نُعيده إلى نموذجه ليصحّح ويعيد المحاولة.
            // أما فشل عرض صفحة فالرجوع إليها يُنتج حلقة، فنُخرجه منها.
            return $request->isMethod('GET')
                ? redirect()->route('dashboard')->with('error', $message)
                : back()->with('error', $message);
        });
    })->create();
