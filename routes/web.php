<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LanguageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CourtSessionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FeasibilityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HealthController;

Route::get('/health', HealthController::class)->name('health');
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\CaseFileController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\FinanceController;

// Language switch (public)
Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// Auth routes (guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Portfolio site
Route::get('/portfolio/{path?}', function ($path = null) {
    $base = realpath(public_path('portfolio'));
    if (!$base || !str_starts_with($base, realpath(public_path()))) {
        abort(404);
    }
    $file = $path ? realpath($base . '/' . $path) : $base . '/index.html';
    if (!$file || !str_starts_with($file, $base)) {
        abort(404);
    }
    if (is_dir($file)) {
        $file = rtrim($file, '/\\') . '/index.html';
    }
    if (file_exists($file)) {
        $exts = ['css' => 'text/css', 'js' => 'application/javascript', 'html' => 'text/html', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'json' => 'application/json', 'ico' => 'image/x-icon'];
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return response(file_get_contents($file), 200, ['Content-Type' => $exts[$ext] ?? 'application/octet-stream']);
    }
    abort(404);
})->where('path', '.*');

// Redirect root to dashboard or login
Route::get('/', fn () => redirect()->route('dashboard'));

// Protected routes
Route::middleware(['auth', 'active'])->group(function () {
    
    // Rate limit all protected routes
    Route::middleware('throttle:120,1')->group(function () {
    
    // Dashboard - all roles
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Global Search API
    Route::get('/search', function (\Illuminate\Http\Request $request) {
        $raw = $request->get('q');
        if (strlen($raw) < 2) return response()->json([]);
        $query = str_replace(['%', '_'], ['\\%', '\\_'], $raw);

        $user = auth()->user();
        $results = collect();

        if (in_array($user->role, ['developer', 'admin', 'lawyer', 'staff'])) {
            $cases = \App\Models\LegalCase::where(function ($q) use ($query) {
                $q->where('case_number', 'like', "%{$query}%")->orWhere('title', 'like', "%{$query}%");
            });
            if ($user->isLawyer()) $cases->where('lawyer_id', $user->id);
            $cases->limit(5)->get()->each(function ($c) use ($results) {
                $results->push(['type' => 'case', 'label' => $c->case_number . ' - ' . $c->title, 'url' => route('cases.show', $c)]);
            });

            $clients = \App\Models\Client::where('name', 'like', "%{$query}%");
            if ($user->isLawyer()) {
                $clients->where(function ($q) use ($user) {
                    $q->whereHas('cases', fn($cq) => $cq->where('lawyer_id', $user->id))->orWhereDoesntHave('cases');
                });
            }
            $clients->limit(5)->get()->each(function ($c) use ($results) {
                $results->push(['type' => 'client', 'label' => '👤 ' . $c->name, 'url' => route('clients.show', $c)]);
            });
        }

        return response()->json($results->take(10)->values());
    })->name('search');

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
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
    
    // Profile - all roles
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    
    // Cases - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/cases/trashed', [CaseController::class, 'trashed'])->name('cases.trashed');
        Route::post('/cases/{id}/restore', [CaseController::class, 'restore'])->name('cases.restore');
        Route::resource('cases', CaseController::class);
        Route::get('/cases/{case}/summarize', [CaseController::class, 'summarize'])->name('cases.summarize');
        Route::get('/cases/{case}/file', [CaseFileController::class, 'download'])->name('cases.file');
        Route::post('/cases/detect-overdue', [CaseController::class, 'autoDetectOverdue'])->name('cases.detectOverdue');
    });
    
    // Court Sessions - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::resource('sessions', CourtSessionController::class);
        Route::get('/sessions/today/list', [CourtSessionController::class, 'today'])->name('sessions.today');
    });
    
    // Tasks - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::resource('tasks', TaskController::class);
        Route::get('/my-tasks', [TaskController::class, 'myTasks'])->name('tasks.my');
    });
    
    // Documents - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::get('/documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    });

    // Reports & Export - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/reports', [ExportController::class, 'index'])->name('reports.index');
        Route::get('/export/cases', [ExportController::class, 'cases'])->name('export.cases');
        Route::get('/export/sessions', [ExportController::class, 'sessions'])->name('export.sessions');
        Route::get('/export/tasks', [ExportController::class, 'tasks'])->name('export.tasks');
        Route::get('/export/clients', [ExportController::class, 'clients'])->name('export.clients');
        Route::get('/export/all', [ExportController::class, 'all'])->name('export.all');
    });
    
    // Clients - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/clients/trashed', [ClientController::class, 'trashed'])->name('clients.trashed');
        Route::post('/clients/{id}/restore', [ClientController::class, 'restore'])->name('clients.restore');
        Route::post('/clients/ajax', [ClientController::class, 'storeAjax'])->name('clients.ajax');
        Route::resource('clients', ClientController::class);
    });
    
    // Users & Admin - developer, admin (أو بصلاحية محددة)
    Route::resource('users', UserController::class)->middleware('role:developer,admin,permission:users.view');
    Route::get('/feasibility', [FeasibilityController::class, 'index'])->middleware('role:developer,admin,permission:feasibility.view')->name('feasibility.index');
    Route::get('/audit-log', [AuditLogController::class, 'index'])->middleware('role:developer,admin,permission:audit_log.view')->name('audit-log.index');
    Route::get('/settings', [SettingController::class, 'index'])->middleware('role:developer,admin,permission:settings.manage')->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->middleware('role:developer,admin,permission:settings.manage')->name('settings.update');

    // Chat - developer, admin, lawyer, staff
    Route::middleware('role:developer,admin,lawyer,staff')->group(function () {
        Route::get('/chat', [App\Http\Controllers\ChatController::class, 'index'])->name('chat.index');
        Route::get('/chat/{conversation}', [App\Http\Controllers\ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat', [App\Http\Controllers\ChatController::class, 'store'])->name('chat.store');
        Route::post('/chat/{conversation}/messages', [App\Http\Controllers\ChatController::class, 'sendMessage'])->name('chat.messages.send');
        Route::get('/chat/{conversation}/messages', [App\Http\Controllers\ChatController::class, 'fetchMessages'])->name('chat.messages.fetch');
        Route::get('/chat/unread/count', [App\Http\Controllers\ChatController::class, 'unreadCount'])->name('chat.unread');
    });

    // Backup - developer, admin (أو بصلاحية backup.manage)
    Route::get('/backup', [BackupController::class, 'index'])->middleware('role:developer,admin,permission:backup.manage')->name('backup.index');
    Route::post('/backup/create', [BackupController::class, 'create'])->middleware('role:developer,admin,permission:backup.manage', 'throttle:3,10')->name('backup.create');
    Route::get('/backup/{filename}/download', [BackupController::class, 'download'])->middleware('role:developer,admin,permission:backup.manage')->name('backup.download');
    Route::post('/backup/upload-restore', [BackupController::class, 'uploadRestore'])->middleware('role:developer,admin,permission:backup.manage', 'throttle:10,60')->name('backup.upload-restore');
    Route::post('/backup/{filename}/restore', [BackupController::class, 'restore'])->middleware('role:developer,admin,permission:backup.manage', 'throttle:10,60')->name('backup.restore');
    Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->middleware('role:developer,admin,permission:backup.manage')->name('backup.destroy');

    // HR - developer, admin
    Route::middleware('role:developer,admin')->group(function () {
        Route::get('/hr', [HrController::class, 'index'])->name('hr.index');
        Route::post('/hr/performance', [HrController::class, 'storePerformance'])->name('hr.performance.store');
        Route::delete('/hr/performance/{performance}', [HrController::class, 'destroyPerformance'])->name('hr.performance.destroy');
        Route::post('/hr/bonuses', [HrController::class, 'storeBonus'])->name('hr.bonuses.store');
        Route::delete('/hr/bonuses/{bonus}', [HrController::class, 'destroyBonus'])->name('hr.bonuses.destroy');
        Route::post('/hr/penalties', [HrController::class, 'storePenalty'])->name('hr.penalties.store');
        Route::delete('/hr/penalties/{penalty}', [HrController::class, 'destroyPenalty'])->name('hr.penalties.destroy');
        Route::post('/hr/leaves', [HrController::class, 'storeLeave'])->name('hr.leaves.store');
        Route::post('/hr/leaves/{leave}/approve', [HrController::class, 'approveLeave'])->name('hr.leaves.approve');
        Route::post('/hr/leaves/{leave}/reject', [HrController::class, 'rejectLeave'])->name('hr.leaves.reject');
        Route::delete('/hr/leaves/{leave}', [HrController::class, 'destroyLeave'])->name('hr.leaves.destroy');
    });

    // Finance - developer, admin
    Route::middleware('role:developer,admin')->group(function () {
        Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction'])->name('finance.transactions.store');
        Route::delete('/finance/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->name('finance.transactions.destroy');
        Route::post('/finance/invoices', [FinanceController::class, 'storeInvoice'])->name('finance.invoices.store');
        Route::post('/finance/invoices/{invoice}/pay', [FinanceController::class, 'payInvoice'])->name('finance.invoices.pay');
        Route::delete('/finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->name('finance.invoices.destroy');
        Route::post('/finance/fees', [FinanceController::class, 'storeFee'])->name('finance.fees.store');
        Route::delete('/finance/fees/{fee}', [FinanceController::class, 'destroyFee'])->name('finance.fees.destroy');
    });

    // Client portal - client role
    Route::middleware('role:client')->prefix('my')->group(function () {
        Route::get('/cases', [ClientPortalController::class, 'cases'])->name('client.cases');
        Route::get('/cases/{case}', [ClientPortalController::class, 'showCase'])->name('client.cases.show');
        Route::get('/sessions', [ClientPortalController::class, 'sessions'])->name('client.sessions');
        Route::get('/documents', [ClientPortalController::class, 'documents'])->name('client.documents');
    });

    }); // end throttle group
});
