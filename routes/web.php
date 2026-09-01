<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LanguageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttentionController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CourtSessionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FeasibilityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MarketingPageController;

Route::get('/health', HealthController::class)->name('health');
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\CaseFileController;
use App\Http\Controllers\CaseActivityController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LawyerEvaluationController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\FinanceController;

// شعار المكتب (عام: يظهر في شاشة الدخول والفاتورة قبل المصادقة)
Route::get('/office/logo', [App\Http\Controllers\OfficeBrandController::class, 'show'])->name('office.logo');

// Language switch (public)
Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// Auth routes (guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ============================================================
// الصفحات التسويقية العامة (لا تتطلب تسجيل دخول)
// ============================================================
Route::get('/register', [MarketingPageController::class, 'register'])->name('marketing.register');
Route::post('/register', [MarketingPageController::class, 'storeRegister'])->name('marketing.register.store')->middleware('throttle:5,10');
Route::get('/features', [MarketingPageController::class, 'features'])->name('marketing.features');
Route::get('/pricing', [MarketingPageController::class, 'pricing'])->name('marketing.pricing');
Route::get('/faq', [MarketingPageController::class, 'faq'])->name('marketing.faq');
Route::get('/blog', [MarketingPageController::class, 'blog'])->name('marketing.blog');
Route::get('/contact', [MarketingPageController::class, 'contact'])->name('marketing.contact');
Route::get('/legal/privacy', [MarketingPageController::class, 'privacy'])->name('marketing.privacy');
Route::get('/legal/terms', [MarketingPageController::class, 'terms'])->name('marketing.terms');
Route::get('/guide', [MarketingPageController::class, 'guide'])->name('marketing.guide');

// Keep-alive لتجديد الجلسة عند السيرفر أثناء تنبيه انتهاء الجلسة
Route::post('/session/keepalive', function (Illuminate\Http\Request $request) {
    $request->session()->put('_keepalive', now()->timestamp);

    return response()->json(['ok' => true]);
})->name('session.keepalive')->middleware('auth');

// Redirect root to dashboard or login
Route::get('/', fn () => redirect()->route('dashboard'));

// Public client portal - no auth required
/*
|--------------------------------------------------------------------------
| بوابة العملاء
|--------------------------------------------------------------------------
| ‏/client-access يبقى مدخل البوابة كما كان — الروابط القديمة لا تُكسر.
| الدخول بخطوتين: رقم الهوية، ثم آخر ٣ أرقام من الهاتف المسجَّل.
| كل صفحة داخل البوابة خلف حارس يتحقّق من الجلسة ومن تفعيل المكتب لها.
*/
Route::get('/client-access', [App\Http\Controllers\ClientAccessController::class, 'showLogin'])->name('client.access');
Route::post('/client-access', [App\Http\Controllers\ClientAccessController::class, 'lookup'])
    ->middleware('throttle:20,10')->name('client.access.lookup');
Route::post('/client-access/verify', [App\Http\Controllers\ClientAccessController::class, 'verify'])
    ->middleware('throttle:20,10')->name('client.access.verify');

// الدخول برمز واتساب: طلبُ الرمز محدودٌ بشدّة — الإرسالُ رسالةٌ حقيقية
Route::post('/client-access/otp', [App\Http\Controllers\ClientAccessController::class, 'otpSend'])
    ->middleware('throttle:6,10')->name('client.access.otp');
Route::post('/client-access/otp/verify', [App\Http\Controllers\ClientAccessController::class, 'otpVerify'])
    ->middleware('throttle:20,10')->name('client.access.otp.verify');
Route::post('/client-access/logout', [App\Http\Controllers\ClientAccessController::class, 'logout'])->name('client.access.logout');

Route::middleware('client.portal')->prefix('client-access')->group(function () {
    Route::get('/home', [App\Http\Controllers\ClientAccessController::class, 'home'])->name('client.portal.home');
    Route::get('/cases', [App\Http\Controllers\ClientAccessController::class, 'cases'])->name('client.portal.cases');
    Route::get('/cases/{case}', [App\Http\Controllers\ClientAccessController::class, 'showCase'])->name('client.portal.case');
    Route::get('/documents/{document}', [App\Http\Controllers\ClientAccessController::class, 'document'])->name('client.portal.document');
    Route::get('/account', [App\Http\Controllers\ClientAccessController::class, 'account'])->name('client.portal.account');
    Route::get('/notifications', [App\Http\Controllers\ClientAccessController::class, 'notifications'])->name('client.portal.notifications');
});

/*
| رابطُ الدخول المرسَل في واتساب.
|
| المسارُ قصيرٌ لأنّه يُلصَق في رسالة: كلُّ محرفٍ زائدٍ يقصّه بعضُ
| العملاء أو يلتفّ به السطر. والرمزُ نفسُه ٦٤ محرفاً لا يُخمَّن،
| والحدُّ يمنع تجريبَ الرموز بالجملة.
*/
Route::get('/p/{token}', [App\Http\Controllers\ClientLinkController::class, 'open'])
    ->where('token', '[A-Za-z0-9]{32,80}')
    ->middleware('throttle:30,1')
    ->name('client.link.open');

// توافق: الرابط القديم لصفحة القضية يوجّه إلى مقابله الجديد
Route::get('/client-access/case/{case}', function (string $case) {
    return redirect()->route('client.portal.case', $case);
})->name('client.access.case');

/*
|--------------------------------------------------------------------------
| ويبهوك واتساب — عامٌّ بالضرورة، محميٌّ بالتوقيع
|--------------------------------------------------------------------------
| Meta تنادي هذا العنوان من خوادمها بلا جلسةٍ ولا رمز CSRF، فلا سبيل
| إلى وضعه خلف تسجيل دخول. والحمايةُ ليست إخفاءَ العنوان بل التحقّق:
| مصافحةُ GET تُقارَن بـhash_equals برمز تحقّقٍ مولَّد لا يكتبه إنسان،
| وطلبُ POST يُرفض ما لم يحمل توقيع HMAC-SHA256 صحيحاً بسرّ تطبيق
| هذا المكتب — والسرُّ يخصّه وحده، فلا يوقّع أحدٌ نيابةً عنه.
|
| والخانق ٦٠٠/دقيقة سخيٌّ عمداً: مكتبٌ نشط قد يستقبل دفعةَ إشعاراتٍ
| بعد انقطاع، وخنقُها يجعل Meta تُعيد الإرسال فيتفاقم الازدحام.
*/
Route::middleware('throttle:600,1')->group(function () {
    Route::get('/webhooks/whatsapp', [App\Http\Controllers\WhatsAppWebhookController::class, 'challenge'])
        ->name('whatsapp.webhook.challenge');
    Route::post('/webhooks/whatsapp', [App\Http\Controllers\WhatsAppWebhookController::class, 'receive'])
        ->name('whatsapp.webhook.receive');
    // جسرُ واتساب ويب: السرُّ في المسار — الجسرُ لا يوقّع حمولاتِه،
    // فمن لا يعرف السرَّ لا يجد العنوان أصلاً
    Route::post('/webhooks/evolution/{secret}', [App\Http\Controllers\WhatsAppWebhookController::class, 'evolution'])
        ->where('secret', '[A-Za-z0-9]{20,80}')
        ->name('whatsapp.webhook.evolution');
});

// Subscription expired page (reachable while authenticated, not gated)
Route::middleware('auth')->get('/subscription-expired', function () {
    return view('subscription.expired');
})->name('subscription.expired');

// صفحة الصيانة — حالة مستقلة عن انتهاء الاشتراك
Route::middleware('auth')->get('/maintenance', function () {
    return response()->view('maintenance.index', [], 503);
})->name('maintenance.page');

// Protected routes
Route::middleware(['auth', 'active', 'subscription'])->group(function () {
    
    // Rate limit all protected routes
    Route::middleware('throttle:120,1')->group(function () {
    
    // لوحة المتابعة ومركز الانتباه: يفتحهما كل دور، ومركز الانتباه
    // يُرشّح بالدور فيخرج فارغاً للموكّل — وله اختبار يُثبته.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/attention', [AttentionController::class, 'index'])->name('attention.index');

    // المساعد القانوني — لفريق المكتب وحده.
    //
    // كان مكتوباً بجانبه «all roles» والمقصود «كل أدوار الفريق»، لكن
    // الوسيط لم يقل ذلك. وتعليمة المساعد تحقن قائمة قضايا المكتب
    // بأسماء موكّليها ومحاكمها — فحسابٌ دوره «موكّل» كان يبلغه ويسأله
    // عنها. النيّة كانت صحيحة والتعبير عنها ناقصاً.
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/ai-assistant/history', [App\Http\Controllers\AssistantController::class, 'history'])->name('assistant.history');
        // ‏٢٠ طلباً في الدقيقة تكفي أسرع محامٍ، وتوقف أي إغراق (§90)
        Route::post('/ai-assistant', [App\Http\Controllers\AssistantController::class, 'chat'])->middleware('throttle:20,1')->name('assistant.chat');
        Route::post('/ai-assistant/clear', [App\Http\Controllers\AssistantController::class, 'clear'])->name('assistant.clear');
    });

    // صفحة تعريف مُداوَلة الداخلية
    Route::get('/about', function () {
        return view('about.index');
    })->name('about');

    // تفضيل المظهر — يخص المستخدم الحالي فقط
    Route::post('/appearance', [App\Http\Controllers\AppearanceController::class, 'update'])->name('appearance.update');

    // User Guide (داخل النظام)
    Route::get('/guide/system', function () {
        return view('guide.index');
    })->name('guide');

    // صندوق الاقتراحات — لفريق المكتب وحده، والقيد معلن هنا لا مستنتَجاً من طبقة أعلى
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/suggestions', [App\Http\Controllers\SuggestionController::class, 'index'])->name('suggestions.index');
        // بلا throttle هنا: كان يستهلك محاولةً حتى على خطأ تحقّق، فمن
        // كتب نصّاً قصيراً خمس مرّات يُحبس عشر دقائق. الحدّ صار داخل
        // المتحكّم بعد نجاح التحقّق — انظر SuggestionController::store
        Route::post('/suggestions', [App\Http\Controllers\SuggestionController::class, 'store'])->name('suggestions.store');
        // الحذف: صاحبه أو مدير المكتب أو المطوّر — والشرط في الخادم
        Route::delete('/suggestions/{suggestion}', [App\Http\Controllers\SuggestionController::class, 'destroy'])->name('suggestions.destroy');
    });
    
    // Global Search API
    Route::get('/search', function (\Illuminate\Http\Request $request) {
        $raw = $request->get('q');
        if (strlen($raw) < 2) return response()->json([]);
        $query = str_replace(['%', '_'], ['\\%', '\\_'], $raw);

        $user = auth()->user();
        $isLawyer = $user && $user->isLawyer();
        $results = collect();

        if (in_array($user->role, ['developer', 'admin', 'lawyer', 'staff'])) {
            $cases = \App\Models\LegalCase::with('client')
                ->where(function ($q) use ($query) {
                    $q->where('case_number', 'like', "%{$query}%")
                      ->orWhere('office_case_number', 'like', "%{$query}%")
                      ->orWhere('title', 'like', "%{$query}%")
                      ->orWhere('court', 'like', "%{$query}%")
                      ->orWhere('opponent_phone', 'like', "%{$query}%");
                })->limit(5)->get();
            foreach ($cases as $c) {
                $label = '#' . $c->office_case_number;
                if ($c->case_number) $label .= ' - ' . $c->case_number;
                $label .= ' - ' . $c->title;
                if ($c->client) $label .= ' - ' . $c->client->phone . ' - ' . $c->client->name;
                $results->push(['type' => 'case', 'label' => $label, 'url' => route('cases.show', $c)]);
            }

            $clients = \App\Models\Client::where('name', 'like', "%{$query}%")
                ->limit(5)->get();
            foreach ($clients as $c) {
                $results->push(['type' => 'client', 'label' => $c->name . ' - ' . $c->phone, 'url' => route('clients.show', $c)]);
            }

            $sessions = \App\Models\Session::with('case')
                ->when($isLawyer, fn ($q) => $q->whereHas('case', fn ($cq) => $cq->where('lawyer_id', $user->id)))
                ->where(function ($q) use ($query) {
                    $q->where('location', 'like', "%{$query}%")
                      ->orWhere('notes', 'like', "%{$query}%");
                })->limit(5)->get();
            foreach ($sessions as $s) {
                $label = 'جلسة - ' . ($s->case->case_number ?? '') . ' - ' . $s->location;
                $results->push(['type' => 'session', 'label' => $label, 'url' => route('sessions.show', $s)]);
            }

            $tasks = \App\Models\Task::with('case')
                ->when($isLawyer, fn ($q) => $q->where('assigned_to', $user->id))
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })->limit(5)->get();
            foreach ($tasks as $t) {
                $label = 'مهمة - ' . $t->title . ($t->case ? ' (' . $t->case->case_number . ')' : '');
                $results->push(['type' => 'task', 'label' => $label, 'url' => route('tasks.show', $t)]);
            }
        }

        return response()->json($results->take(15)->values());
    })->name('search');

    // Command Palette - unified search + actions
    Route::get('/command', App\Http\Controllers\CommandController::class)->name('command');

    // Natural Language → Actions (Speech-to-Action)
    Route::post('/nl/actions/parse', [App\Http\Controllers\NaturalActionController::class, 'parse'])->name('nl.parse');
    Route::post('/nl/actions/confirm', [App\Http\Controllers\NaturalActionController::class, 'confirm'])->name('nl.confirm');

    // Sync - lightweight polling endpoint for real-time updates
    Route::get('/sync', function () {
        $tables = ['cases', 'tasks', 'sessions', 'clients', 'notifications'];
        $max = null;
        foreach ($tables as $table) {
            try {
                $latest = \Illuminate\Support\Facades\DB::table($table)->whereNull('deleted_at')->max('updated_at');
                if ($latest && (!$max || $latest > $max)) {
                    $max = $latest;
                }
            } catch (\Exception $e) {}
        }
        return response()->json([
            'updated_at' => $max ?? now()->toDateTimeString(),
        ]);
    })->name('sync');
    
    // Notifications - all roles
    Route::middleware('feature:notifications')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
        Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
        Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    });
    
    // Profile - all roles
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Announcements - all roles
    Route::post('/announcements/{announcement}/seen', [AnnouncementController::class, 'markSeen'])->name('announcements.seen');
    
    // Cases - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:cases'])->group(function () {
        Route::get('/cases/trashed', [CaseController::class, 'trashed'])->name('cases.trashed');
        Route::post('/cases/{id}/restore', [CaseController::class, 'restore'])->name('cases.restore');
        Route::resource('cases', CaseController::class);
        Route::get('/cases/{case}/summarize', [CaseController::class, 'summarize'])->name('cases.summarize');
        Route::get('/cases/{case}/file', [CaseFileController::class, 'download'])->name('cases.file');
        Route::post('/cases/detect-overdue', [CaseController::class, 'autoDetectOverdue'])->name('cases.detectOverdue');
        Route::get('/cases/monthly/list', [CaseController::class, 'monthly'])->name('cases.monthly');
        Route::get('/cases/monthly/data', [CaseController::class, 'monthlyData'])->name('cases.monthly.data');
        Route::post('/cases/{case}/analyze', [CaseController::class, 'analyze'])->name('cases.analyze');
        Route::post('/cases/{case}/ai-chat', [CaseController::class, 'aiChat'])->name('cases.ai_chat');
        Route::post('/cases/{case}/send-portal-message', [CaseController::class, 'sendPortalMessage'])->name('cases.sendPortalMessage');
        Route::post('/cases/{case}/activities', [CaseActivityController::class, 'store'])->name('cases.activities.store');
        Route::post('/cases/{case}/checklist/{item}/toggle', [CaseController::class, 'toggleChecklistItem'])->name('cases.checklist.toggle');
        Route::get('/cases/{case}/timeline', [CaseActivityController::class, 'timeline'])->name('cases.timeline');
        Route::delete('/cases/{case}/activities/{activity}', [CaseActivityController::class, 'destroy'])->name('cases.activities.destroy');
    });
    
    // Court Sessions - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:sessions'])->group(function () {
        Route::post('/sessions/quick', [CourtSessionController::class, 'quickStore'])->name('sessions.quick');
        // قبل resource عمداً: وإلا التقط sessions/{session} كلمةَ print معرّفاً
        Route::get('/sessions/print', [CourtSessionController::class, 'print'])->name('sessions.print');
        Route::resource('sessions', CourtSessionController::class);
        Route::get('/sessions/today/list', [CourtSessionController::class, 'today'])->name('sessions.today');
    });
    
    // Tasks - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:tasks'])->group(function () {
        // تغيير الحالة وحده — قبله كان زرّ «إكمال المهمة» يمرّ عبر
        // tasks.update فيرسل كل حقول المهمة كما رُسمت في الصفحة، فإن
        // عدّلها زميلٌ في تلك اللحظة عادت تعديلاتُه إلى ما كانت عليه
        // بلا إنذار. المسار هنا لا يلمس غير الحالة.
        Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus'])->name('tasks.status');
        Route::resource('tasks', TaskController::class);
        Route::get('/my-tasks', [TaskController::class, 'myTasks'])->name('tasks.my');
    });
    
    // Documents - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:documents'])->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::post('/documents/{document}/client-visibility', [DocumentController::class, 'toggleClientVisibility'])->name('documents.clientVisibility');

        // §2: مجلدات القضية ونقل المستندات بينها — تنظيم لا يمسّ المحتوى
        Route::post('/cases/{case}/folders', [App\Http\Controllers\CaseFolderController::class, 'store'])->name('case-folders.store');
        Route::put('/case-folders/{folder}', [App\Http\Controllers\CaseFolderController::class, 'update'])->name('case-folders.update');
        Route::delete('/case-folders/{folder}', [App\Http\Controllers\CaseFolderController::class, 'destroy'])->name('case-folders.destroy');
        Route::post('/documents/{document}/move', [App\Http\Controllers\CaseFolderController::class, 'moveDocument'])->name('documents.move');

        // التنزيل والمعاينة لفريق المكتب — كانا بالخطأ داخل مجموعة إدارة
        // «أنواع المستندات» المحصورة بمدير المكتب، فلم يستطع محامٍ ولا
        // موظف تنزيل مستند واحد ولا معاينته. والصلاحية لكل مستند على
        // حدة تبقى مفروضة في المتحكّم: الخاص لرافعه، والفريق لفريقه.
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    });

    // أنواع المستندات — إدارة لمدير المكتب
    Route::middleware(['role:developer,admin,permission:settings.manage'])->group(function () {
        Route::get('/document-types', [App\Http\Controllers\DocumentTypeController::class, 'index'])->name('document-types.index');
        Route::post('/document-types', [App\Http\Controllers\DocumentTypeController::class, 'store'])->name('document-types.store');
        Route::put('/document-types/{documentType}', [App\Http\Controllers\DocumentTypeController::class, 'update'])->name('document-types.update');
        Route::post('/document-types/{documentType}/toggle', [App\Http\Controllers\DocumentTypeController::class, 'toggle'])->name('document-types.toggle');
        Route::delete('/document-types/{documentType}', [App\Http\Controllers\DocumentTypeController::class, 'destroy'])->name('document-types.destroy');
    });

    // Reports & Export - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:reports'])->group(function () {
        Route::get('/reports', [ExportController::class, 'index'])->name('reports.index');
        Route::get('/export/cases', [ExportController::class, 'cases'])->name('export.cases');
        Route::get('/export/sessions', [ExportController::class, 'sessions'])->name('export.sessions');
        Route::get('/export/tasks', [ExportController::class, 'tasks'])->name('export.tasks');
        Route::get('/export/clients', [ExportController::class, 'clients'])->name('export.clients');
        Route::get('/export/all', [ExportController::class, 'all'])->name('export.all');
    });
    
    // Clients - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:clients'])->group(function () {
        Route::get('/clients/trashed', [ClientController::class, 'trashed'])->name('clients.trashed');
        Route::post('/clients/{id}/restore', [ClientController::class, 'restore'])->name('clients.restore');
        Route::post('/clients/ajax', [ClientController::class, 'storeAjax'])->name('clients.ajax');
        Route::resource('clients', ClientController::class);
    });

    // Evaluations - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff'])->group(function () {
        Route::get('/evaluations', [LawyerEvaluationController::class, 'index'])->name('evaluations.index');
    });
    
    // Users & Admin - all team roles
    Route::resource('users', UserController::class)->middleware(['role:developer,admin', 'feature:users']);

    // القوالب الذكية — للإدارة أو من يملك صلاحية templates.manage صراحةً
    Route::middleware(['role:developer,admin,permission:templates.manage', 'feature:case_templates'])->group(function () {
        Route::resource('case-templates', App\Http\Controllers\CaseTemplateController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::post('/case-templates/ai-draft', [App\Http\Controllers\CaseTemplateController::class, 'aiDraft'])->middleware('throttle:10,10')->name('case-templates.ai-draft');
        Route::post('/case-templates/seed-defaults', [App\Http\Controllers\CaseTemplateController::class, 'seedDefaults'])->name('case-templates.seed');
        Route::post('/case-templates/{case_template}/duplicate', [App\Http\Controllers\CaseTemplateController::class, 'duplicate'])->name('case-templates.duplicate');
        Route::post('/case-templates/{case_template}/toggle', [App\Http\Controllers\CaseTemplateController::class, 'toggle'])->name('case-templates.toggle');
        Route::get('/case-templates/{case_template}/versions', [App\Http\Controllers\CaseTemplateController::class, 'versions'])->name('case-templates.versions');
        Route::post('/case-templates/{case_template}/versions/{version}/restore', [App\Http\Controllers\CaseTemplateController::class, 'restoreVersion'])->whereNumber('version')->name('case-templates.versions.restore');
    });

    // مركز الأتمتة — للإدارة أو من يملك صلاحية automations.manage صراحةً
    Route::middleware(['role:developer,admin,permission:automations.manage', 'feature:automations'])->prefix('automations')->group(function () {
        Route::get('/', [App\Http\Controllers\AutomationController::class, 'index'])->name('automations.index');
        Route::post('/', [App\Http\Controllers\AutomationController::class, 'store'])->name('automations.store');
        Route::get('/runs', [App\Http\Controllers\AutomationController::class, 'runs'])->name('automations.runs');
        Route::post('/seed-defaults', [App\Http\Controllers\AutomationController::class, 'seedDefaults'])->name('automations.seed');
        Route::post('/toggle-engine', [App\Http\Controllers\AutomationController::class, 'toggleEngine'])->name('automations.engine');
        Route::post('/bulk', [App\Http\Controllers\AutomationController::class, 'bulkToggle'])->name('automations.bulk');
        Route::post('/ai-draft', [App\Http\Controllers\AutomationController::class, 'aiDraft'])->middleware('throttle:10,10')->name('automations.ai-draft');
        Route::post('/suggestions/accept', [App\Http\Controllers\AutomationController::class, 'acceptSuggestion'])->name('automations.suggestions.accept');
        Route::post('/suggestions/dismiss', [App\Http\Controllers\AutomationController::class, 'dismissSuggestion'])->name('automations.suggestions.dismiss');
        Route::put('/{automation}', [App\Http\Controllers\AutomationController::class, 'update'])->name('automations.update');
        Route::post('/{automation}/toggle', [App\Http\Controllers\AutomationController::class, 'toggle'])->name('automations.toggle');
        Route::post('/{automation}/duplicate', [App\Http\Controllers\AutomationController::class, 'duplicate'])->name('automations.duplicate');
        Route::get('/{automation}/versions', [App\Http\Controllers\AutomationController::class, 'versions'])->name('automations.versions');
        Route::post('/{automation}/versions/{version}/restore', [App\Http\Controllers\AutomationController::class, 'restoreVersion'])->whereNumber('version')->name('automations.versions.restore');
        Route::post('/{automation}/test', [App\Http\Controllers\AutomationController::class, 'test'])->name('automations.test');
        Route::delete('/{automation}', [App\Http\Controllers\AutomationController::class, 'destroy'])->name('automations.destroy');
    });
    Route::get('/feasibility', [FeasibilityController::class, 'index'])->middleware(['role:developer,admin,permission:feasibility.view', 'feature:feasibility'])->name('feasibility.index');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware(['role:developer,admin,permission:audit_log.view', 'feature:audit_log'])->name('audit-log.index');
    // باقة المكتب واستهلاكه — يراها من يدير المكتب
    Route::get('/plan', [\App\Http\Controllers\PlanController::class, 'index'])
        ->middleware('role:developer,admin')->name('plan.index');
    Route::post('/plan/upgrade', [\App\Http\Controllers\PlanController::class, 'requestUpgrade'])
        ->middleware(['role:developer,admin', 'throttle:5,60'])->name('plan.upgrade');

    Route::get('/settings', [SettingController::class, 'index'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.update');
    // هوية المكتب — نفس صلاحية الإعدادات تماماً
    Route::post('/settings/logo', [App\Http\Controllers\OfficeBrandController::class, 'update'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.logo.update');
    Route::delete('/settings/logo', [App\Http\Controllers\OfficeBrandController::class, 'destroy'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.logo.destroy');

    /*
    | واتساب — صندوق الوارد وإعدادات الربط
    |
    | ثلاث صلاحيات لا واحدة: القراءة غير الردّ، والردّ غير ربط الرقم.
    | موظّفُ الاستقبال يقرأ ويردّ ولا يملك رمز الحساب، ومديرُ المكتب
    | وحده يربط ويفصل — لأنّ الرمز يُرسل باسم المكتب كلِّه.
    */
    // ‏wa.inbox: الصندوقُ مخفيٌّ افتراضاً — الإرسالُ اليدويُّ الحرّ
    // أخطرُ ما على سلامة الرقم، والإشعاراتُ الآلية لا تحتاجه
    Route::middleware(['feature:whatsapp', 'wa.inbox'])->prefix('whatsapp')->group(function () {
        Route::get('/', [App\Http\Controllers\WhatsAppInboxController::class, 'index'])
            ->middleware('role:developer,admin,permission:whatsapp.view')->name('whatsapp.index');
        Route::get('/{conversation}', [App\Http\Controllers\WhatsAppInboxController::class, 'show'])
            ->middleware('role:developer,admin,permission:whatsapp.view')->name('whatsapp.show');

        Route::post('/{conversation}/send', [App\Http\Controllers\WhatsAppInboxController::class, 'send'])
            ->middleware(['role:developer,admin,permission:whatsapp.send', 'throttle:60,1'])->name('whatsapp.send');
        Route::post('/{conversation}/note', [App\Http\Controllers\WhatsAppInboxController::class, 'note'])
            ->middleware('role:developer,admin,permission:whatsapp.send')->name('whatsapp.note');
        Route::post('/{conversation}/assign', [App\Http\Controllers\WhatsAppInboxController::class, 'assign'])
            ->middleware('role:developer,admin,permission:whatsapp.manage')->name('whatsapp.assign');
        Route::post('/{conversation}/link-client', [App\Http\Controllers\WhatsAppInboxController::class, 'linkClient'])
            ->middleware('role:developer,admin,permission:whatsapp.manage')->name('whatsapp.link-client');
        Route::post('/{conversation}/link-case', [App\Http\Controllers\WhatsAppInboxController::class, 'linkCase'])
            ->middleware('role:developer,admin,permission:whatsapp.manage')->name('whatsapp.link-case');
        Route::post('/{conversation}/handoff', [App\Http\Controllers\WhatsAppInboxController::class, 'handoff'])
            ->middleware('role:developer,admin,permission:whatsapp.send')->name('whatsapp.handoff');
        Route::post('/{conversation}/close', [App\Http\Controllers\WhatsAppInboxController::class, 'close'])
            ->middleware('role:developer,admin,permission:whatsapp.manage')->name('whatsapp.close');
        Route::post('/message/{message}/document', [App\Http\Controllers\WhatsAppInboxController::class, 'saveDocument'])
            ->middleware('role:developer,admin,permission:whatsapp.manage')->name('whatsapp.save-document');
    });

    // ربطُ الرقم — مع إعدادات المكتب، لمن يدير الإعدادات وحده
    Route::post('/settings/whatsapp', [App\Http\Controllers\WhatsAppSettingsController::class, 'update'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.whatsapp.update');
    Route::delete('/settings/whatsapp', [App\Http\Controllers\WhatsAppSettingsController::class, 'disconnect'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.whatsapp.disconnect');
    Route::post('/settings/whatsapp/test', [App\Http\Controllers\WhatsAppSettingsController::class, 'test'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings', 'throttle:10,1'])->name('settings.whatsapp.test');
    // فحصُ خطوات الربط — محدودٌ كالفحص: ثلاثةُ نداءاتٍ إلى Meta لكلّ ضغطة
    Route::post('/settings/whatsapp/checkup', [App\Http\Controllers\WhatsAppSettingsController::class, 'checkup'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings', 'throttle:10,1'])->name('settings.whatsapp.checkup');
    // إتمامُ الربط نيابةً عن المكتب — يكتب عند Meta، فحدُّه أضيق
    Route::post('/settings/whatsapp/autowire', [App\Http\Controllers\WhatsAppSettingsController::class, 'autowire'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings', 'throttle:6,1'])->name('settings.whatsapp.autowire');
    // اقترانُ جسر واتساب ويب — رمزُ المسح وحالتُه
    Route::post('/settings/whatsapp/pair', [App\Http\Controllers\WhatsAppSettingsController::class, 'pair'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings', 'throttle:20,1'])->name('settings.whatsapp.pair');
    Route::get('/settings/whatsapp/pair-state', [App\Http\Controllers\WhatsAppSettingsController::class, 'pairState'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings', 'throttle:60,1'])->name('settings.whatsapp.pair-state');
    Route::post('/settings/whatsapp/templates', [App\Http\Controllers\WhatsAppSettingsController::class, 'syncTemplates'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings', 'throttle:10,1'])->name('settings.whatsapp.templates.sync');

    // إعدادات الذكاء الاصطناعي — خاصة بهذا المكتب، ومقصورة على من يدير الإعدادات
    Route::post('/settings/ai', [App\Http\Controllers\AiSettingsController::class, 'update'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.ai.update');
    Route::delete('/settings/ai', [App\Http\Controllers\AiSettingsController::class, 'destroy'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.ai.destroy');
    Route::post('/settings/ai/test', [App\Http\Controllers\AiSettingsController::class, 'test'])->middleware(['role:developer,admin,permission:settings.manage', 'feature:settings'])->name('settings.ai.test');

    // طلبات التسجيل من الموقع التعريفي
    Route::get('/register-requests', [MarketingPageController::class, 'requests'])->middleware(['role:developer,admin'])->name('marketing.requests');
    Route::post('/register-requests/{registration_request}/status', [MarketingPageController::class, 'updateStatus'])->middleware(['role:developer,admin'])->name('marketing.requests.status');

    // Chat - developer, admin, lawyer, staff
    Route::middleware(['role:developer,admin,lawyer,staff', 'feature:chat'])->group(function () {
        Route::get('/chat', [App\Http\Controllers\ChatController::class, 'index'])->name('chat.index');
        Route::get('/chat/{conversation}', [App\Http\Controllers\ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat', [App\Http\Controllers\ChatController::class, 'store'])->name('chat.store');
        Route::post('/chat/{conversation}/messages', [App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.messages.send');
        Route::get('/chat/{conversation}/messages', [App\Http\Controllers\ChatController::class, 'fetchMessages'])->name('chat.messages.fetch');
        Route::put('/chat/messages/{message}', [App\Http\Controllers\ChatController::class, 'editMessage'])->name('chat.messages.edit');
        Route::delete('/chat/messages/{message}', [App\Http\Controllers\ChatController::class, 'deleteMessage'])->name('chat.messages.destroy');
        Route::get('/chat/unread/count', [App\Http\Controllers\ChatController::class, 'unreadCount'])->name('chat.unread');
        Route::get('/chat/messages/{message}/attachment', [App\Http\Controllers\ChatController::class, 'attachment'])->name('chat.attachment');
    });

    // Backup - developer, admin (أو بصلاحية backup.manage)
    Route::get('/backup', [BackupController::class, 'index'])->middleware('role:developer,admin,permission:backup.manage')->name('backup.index');
    Route::post('/backup/create', [BackupController::class, 'create'])->middleware('role:developer,admin,permission:backup.manage', 'throttle:30,10')->name('backup.create');
    Route::get('/backup/{filename}/download', [BackupController::class, 'download'])->middleware('role:developer,admin,permission:backup.manage')->name('backup.download');
    Route::post('/backup/upload-restore', [BackupController::class, 'uploadRestore'])->middleware('role:developer,admin,permission:backup.manage', 'throttle:10,60')->name('backup.upload-restore');
    Route::post('/backup/{filename}/restore', [BackupController::class, 'restore'])->middleware('role:developer,admin,permission:backup.manage', 'throttle:10,60')->name('backup.restore');
    Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->middleware('role:developer,admin,permission:backup.manage')->name('backup.destroy');

    // HR - all authenticated non-client users (controller handles per-role logic)
    Route::middleware(['auth', 'active', 'role:developer,admin,lawyer,staff', 'feature:hr'])->group(function () {
        Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
        Route::post('/hr/attendance/check-in', [HrController::class, 'checkIn'])->name('hr.attendance.checkin');
        Route::post('/hr/attendance/check-out', [HrController::class, 'checkOut'])->name('hr.attendance.checkout');
        Route::post('/hr/leaves', [HrController::class, 'storeLeave'])->name('hr.leaves.store');
        Route::post('/hr/performance', [HrController::class, 'storePerformance'])->name('hr.performance.store');
        Route::delete('/hr/performance/{performance}', [HrController::class, 'destroyPerformance'])->name('hr.performance.destroy');
        Route::post('/hr/bonuses', [HrController::class, 'storeBonus'])->name('hr.bonuses.store');
        Route::delete('/hr/bonuses/{bonus}', [HrController::class, 'destroyBonus'])->name('hr.bonuses.destroy');
        Route::post('/hr/penalties', [HrController::class, 'storePenalty'])->name('hr.penalties.store');
        Route::delete('/hr/penalties/{penalty}', [HrController::class, 'destroyPenalty'])->name('hr.penalties.destroy');
        Route::post('/hr/leaves/{leave}/approve', [HrController::class, 'approveLeave'])->name('hr.leaves.approve');
        Route::post('/hr/leaves/{leave}/reject', [HrController::class, 'rejectLeave'])->name('hr.leaves.reject');
        Route::delete('/hr/leaves/{leave}', [HrController::class, 'destroyLeave'])->name('hr.leaves.destroy');

        // ── الحضور ───────────────────────────────────────────────
        // مفتوح لكل موظف: كلٌّ يرى سجلّه، والمدير يرى الفريق —
        // والفصل في الاستعلام لا في الصفحة.
        Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance/keep-present', [App\Http\Controllers\AttendanceController::class, 'keepPresent'])->name('attendance.keep');

        // ── الرواتب: إدارة المكتب وحدها ──────────────────────────
        // الوسيط يحجب lawyer وstaff ما لم تُمنح لهما الصلاحية صراحةً،
        // والمتحكّم يفحص التفويض مرّة أخرى في كل دالّة.
        Route::middleware('role:developer,admin,permission:salaries.manage')->group(function () {
            Route::get('/salaries', [App\Http\Controllers\SalaryController::class, 'index'])->name('salaries.index');
            Route::post('/salaries', [App\Http\Controllers\SalaryController::class, 'store'])->name('salaries.store');
            Route::get('/salaries/{employee}', [App\Http\Controllers\SalaryController::class, 'show'])->name('salaries.show');
            Route::post('/salaries/adjustments', [App\Http\Controllers\SalaryController::class, 'storeAdjustment'])->name('salaries.adjustments.store');
            Route::delete('/salaries/adjustments/{adjustment}', [App\Http\Controllers\SalaryController::class, 'destroyAdjustment'])->name('salaries.adjustments.destroy');
            Route::post('/salaries/settings', [App\Http\Controllers\SalaryController::class, 'updateSettings'])->name('salaries.settings');
            Route::post('/leave-types', [App\Http\Controllers\LeaveTypeController::class, 'store'])->name('leave-types.store');
            Route::put('/leave-types/{leaveType}', [App\Http\Controllers\LeaveTypeController::class, 'update'])->name('leave-types.update');
        });
    });

    // Finance - all authenticated non-client users (controller handles per-role logic)
    Route::middleware(['auth', 'active', 'role:developer,admin,lawyer,staff', 'feature:finance'])->group(function () {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance/transactions/{transaction}', [FinanceController::class, 'showTransaction'])->name('finance.transactions.show');
        Route::get('/finance/transactions/{transaction}/print', [FinanceController::class, 'printTransaction'])->name('finance.transactions.print');
        Route::get('/finance/invoices/{invoice}', [FinanceController::class, 'showInvoice'])->name('finance.invoices.show');
        Route::get('/finance/invoices/{invoice}/print', [FinanceController::class, 'printInvoice'])->name('finance.invoices.print');
        Route::get('/finance/fees/{fee}', [FinanceController::class, 'showFee'])->name('finance.fees.show');
        Route::get('/finance/fees/{fee}/print', [FinanceController::class, 'printFee'])->name('finance.fees.print');
        Route::get('/finance/transactions/{transaction}/attachment', [FinanceController::class, 'transactionAttachment'])->name('finance.transactions.attachment');
        Route::get('/finance/invoices/{invoice}/attachment', [FinanceController::class, 'invoiceAttachment'])->name('finance.invoices.attachment');
        Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction'])->name('finance.transactions.store');
        Route::put('/finance/transactions/{transaction}', [FinanceController::class, 'updateTransaction'])->name('finance.transactions.update');
        Route::delete('/finance/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->name('finance.transactions.destroy');
        Route::post('/finance/invoices', [FinanceController::class, 'storeInvoice'])->name('finance.invoices.store');
        Route::put('/finance/invoices/{invoice}', [FinanceController::class, 'updateInvoice'])->name('finance.invoices.update');
        Route::post('/finance/invoices/{invoice}/pay', [FinanceController::class, 'payInvoice'])->name('finance.invoices.pay');
        Route::delete('/finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->name('finance.invoices.destroy');
        Route::post('/finance/fees', [FinanceController::class, 'storeFee'])->name('finance.fees.store');
        Route::put('/finance/fees/{fee}', [FinanceController::class, 'updateFee'])->name('finance.fees.update');
        Route::delete('/finance/fees/{fee}', [FinanceController::class, 'destroyFee'])->name('finance.fees.destroy');
    });

    // Client portal - client role
    Route::middleware('role:client')->prefix('my')->group(function () {
        Route::get('/cases', [ClientPortalController::class, 'cases'])->name('client.cases');
        Route::get('/cases/{case}', [ClientPortalController::class, 'showCase'])->name('client.cases.show');
        Route::get('/sessions', [ClientPortalController::class, 'sessions'])->name('client.sessions');
        Route::get('/documents', [ClientPortalController::class, 'documents'])->name('client.documents');
    });

    // Developer Panel - developer only
    Route::prefix('developer')->middleware('role:developer')->group(function () {
        Route::get('/', [App\Http\Controllers\DeveloperController::class, 'index'])->name('developer.index');

        // الرد على الاقتراحات وإدارتها — للمطوّر وحده
        Route::post('/suggestions/{suggestion}/reply', [App\Http\Controllers\SuggestionController::class, 'reply'])->name('suggestions.reply');
        Route::post('/suggestions/{suggestion}/status', [App\Http\Controllers\SuggestionController::class, 'setStatus'])->name('suggestions.status');
        Route::put('/suggestions/{suggestion}', [App\Http\Controllers\SuggestionController::class, 'update'])->name('suggestions.update');
        Route::post('/cache-clear', [App\Http\Controllers\DeveloperController::class, 'clearCache'])->name('developer.cache-clear');
        Route::post('/cache-all', [App\Http\Controllers\DeveloperController::class, 'cacheAll'])->name('developer.cache-all');
        Route::post('/optimize', [App\Http\Controllers\DeveloperController::class, 'optimize'])->name('developer.optimize');
        Route::post('/migrate', [App\Http\Controllers\DeveloperController::class, 'migrate'])->name('developer.migrate');
        Route::post('/storage-link', [App\Http\Controllers\DeveloperController::class, 'storageLink'])->name('developer.storage-link');
        Route::get('/features', [App\Http\Controllers\DeveloperController::class, 'features'])->name('developer.features');
        Route::post('/features/toggle', [App\Http\Controllers\DeveloperController::class, 'toggleFeature'])->name('developer.features.toggle');
        Route::post('/automation/toggle', [App\Http\Controllers\DeveloperController::class, 'toggleAutomation'])->name('developer.automation.toggle');
        Route::post('/announcement', [App\Http\Controllers\AnnouncementController::class, 'publish'])->name('announcements.publish');

        // Subscription configuration - developer only (site-level subscription)
        Route::get('/subscription', [App\Http\Controllers\DeveloperSubscriptionController::class, 'config'])->name('developer.subscription.config');
        Route::post('/subscription/activate', [App\Http\Controllers\DeveloperSubscriptionController::class, 'activate'])->name('developer.subscription.activate');
        Route::post('/subscription/suspend', [App\Http\Controllers\DeveloperSubscriptionController::class, 'suspend'])->name('developer.subscription.suspend');
        Route::post('/subscription/reactivate', [App\Http\Controllers\DeveloperSubscriptionController::class, 'reactivate'])->name('developer.subscription.reactivate');
    });

    }); // end throttle group
});
