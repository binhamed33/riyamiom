<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeveloperController extends Controller
{
    public function index(): View
    {
        $phpVersion = phpversion();
        $laravelVersion = app()->version();
        $dbName = DB::connection()->getDatabaseName();
        $dbSize = 0;
        try {
            $dbSize = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$dbName])[0]->size_mb ?? 0;
        } catch (\Exception $e) {}

        $userCount = User::count();
        $logCount = AuditLog::count();
        $recentLogs = AuditLog::with('user')->latest()->limit(20)->get();
        $suggestions = Suggestion::with('user')->latest()->limit(25)->get();
        $currentAnnouncement = Announcement::withCount('reads')->latest()->first();

        $cacheDrivers = [
            'config' => app()->configurationIsCached(),
            'routes' => app()->routesAreCached(),
            'views' => file_exists(base_path('bootstrap/cache/views.php')),
            'events' => file_exists(base_path('bootstrap/cache/events.php')),
        ];

        $diskFree = function_exists('disk_free_space') ? round(disk_free_space(base_path()) / 1024 / 1024 / 1024, 1) : '—';
        $diskTotal = function_exists('disk_total_space') ? round(disk_total_space(base_path()) / 1024 / 1024 / 1024, 1) : '—';

        return view('developer.index', compact(
            'phpVersion', 'laravelVersion', 'dbName', 'dbSize',
            'userCount', 'logCount', 'recentLogs', 'cacheDrivers',
            'diskFree', 'diskTotal', 'suggestions', 'currentAnnouncement'
        ));
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        return redirect()->route('developer.index')->with('success', '🧹 تم مسح جميع أنواع الكاش');
    }

    public function cacheAll()
    {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        return redirect()->route('developer.index')->with('success', '⚡ تم إعادة تخزين الكاش');
    }

    public function optimize()
    {
        Artisan::call('optimize:clear');
        return redirect()->route('developer.index')->with('success', '✨ تم تحسين النظام');
    }

    public function migrate()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->route('developer.index')->with('success', '📦 تم تشغيل الترحيلات بنجاح');
        } catch (\Exception $e) {
            return redirect()->route('developer.index')->with('error', '❌ فشل الترحيل: ' . $e->getMessage());
        }
    }

    public function storageLink()
    {
        try {
            Artisan::call('storage:link');
            return redirect()->route('developer.index')->with('success', '🔗 تم إنشاء رابط التخزين');
        } catch (\Exception $e) {
            return redirect()->route('developer.index')->with('error', '❌ فشل: ' . $e->getMessage());
        }
    }

    public function features(): View
    {
        $features = [
            'cases'        => 'القضايا',
            'tasks'        => 'المهام',
            'sessions'     => 'الجلسات',
            'clients'      => 'العملاء',
            'feasibility'  => 'دراسة الجدوى',
            'hr'           => 'الموارد البشرية',
            'settings'     => 'الإعدادات',
            'reports'      => 'التقارير',
            'chat'         => 'المحادثات',
            'notifications'=> 'الإشعارات',
            'documents'    => 'المستندات',
            'users'        => 'المستخدمين',
            'audit_log'    => 'سجل النشاطات',
            'finance'      => 'المالية',
        ];

        $statuses = [];
        foreach (array_keys($features) as $key) {
            $statuses[$key] = Setting::get('feature_' . $key, '0');
        }

        return view('developer.features', compact('features', 'statuses'));
    }

    public function toggleFeature(Request $request): RedirectResponse
    {
        $key = $request->input('key');
        $value = $request->input('value', '0');

        $allowed = ['cases','tasks','sessions','clients','feasibility','hr','settings','reports','chat','notifications','documents','users','audit_log','finance'];

        if (!in_array($key, $allowed)) {
            return redirect()->route('developer.features')->with('error', '❌ ميزة غير صالحة');
        }

        Setting::set('feature_' . $key, $value, 'features');

        $status = $value === '1' ? 'معطّلة' : 'مفعلة';
        return redirect()->route('developer.features')->with('success', "✅ تم تغيير حالة الميزة إلى $status");
    }
}
