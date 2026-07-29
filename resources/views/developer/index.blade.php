@extends('layouts.app')

@section('title', 'لوحة المطور')

@section('content')
<div class="space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-amber-600">⚙️ لوحة المطور</h1>
        <div class="flex items-center gap-2 text-xs text-gray-400">
            <span>v{{ $laravelVersion }}</span>
            <span class="w-1 h-1 rounded-full bg-gray-200"></span>
            <span>PHP {{ $phpVersion }}</span>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-200 text-green-700 px-5 py-3 rounded-xl text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-100 border border-red-200 text-red-700 px-5 py-3 rounded-xl text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- System Info Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-gray-400 text-xs mb-1">PHP</p>
            <p class="text-lg font-bold text-gray-700">{{ $phpVersion }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-gray-400 text-xs mb-1">Laravel</p>
            <p class="text-lg font-bold text-gray-700">{{ $laravelVersion }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-gray-400 text-xs mb-1">قاعدة البيانات</p>
            <p class="text-lg font-bold text-gray-700 truncate">{{ $dbName }}</p>
            <p class="text-gray-400 text-xs">{{ $dbSize }} MB</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-gray-400 text-xs mb-1">المستخدمين</p>
            <p class="text-lg font-bold text-gray-700">{{ $userCount }}</p>
            <p class="text-gray-400 text-xs">{{ $logCount }} سجل</p>
        </div>
    </div>

    {{-- Storage --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between">
            <p class="text-gray-400 text-xs">المساحة التخزينية</p>
            <p class="text-sm text-gray-600">{{ $diskFree }} GB / {{ $diskTotal }} GB</p>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-amber-600 font-bold mb-4 text-sm uppercase tracking-wider">⚡ إجراءات سريعة</h2>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('developer.cache-clear') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-red-100 border border-red-200 text-red-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition">🧹 مسح الكاش</button>
            </form>
            <form action="{{ route('developer.cache-all') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-amber-100 border border-amber-200 text-amber-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-200 transition">⚡ تخزين الكاش</button>
            </form>
            <form action="{{ route('developer.optimize') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-blue-100 border border-blue-200 text-blue-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-200 transition">✨ تحسين</button>
            </form>
            <form action="{{ route('developer.migrate') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-purple-100 border border-purple-200 text-purple-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-200 transition">📦 ترحيل</button>
            </form>
            <form action="{{ route('developer.storage-link') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-200 transition">🔗 رابط التخزين</button>
            </form>
        </div>
    </div>

    {{-- Cache Status --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-amber-600 font-bold mb-4 text-sm uppercase tracking-wider">💾 حالة الكاش</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($cacheDrivers as $key => $cached)
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl {{ $cached ? 'bg-green-100' : 'bg-red-100' }}">
                    <span class="w-2.5 h-2.5 rounded-full {{ $cached ? 'bg-green-400' : 'bg-red-400' }}"></span>
                    <span class="text-sm text-gray-700">{{ $key }}</span>
                    <span class="text-xs {{ $cached ? 'text-green-700' : 'text-red-700' }}">{{ $cached ? 'مخزن' : 'غير مخزن' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Audit Log --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-amber-600 font-bold text-sm uppercase tracking-wider">📋 آخر النشاطات</h2>
            <a href="{{ route('audit-log.index') }}" class="text-gray-400 text-xs hover:text-amber-600 transition">عرض الكل</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2.5 text-right text-gray-400 text-xs">المستخدم</th>
                        <th class="px-4 py-2.5 text-right text-gray-400 text-xs">الإجراء</th>
                        <th class="px-4 py-2.5 text-right text-gray-400 text-xs">النموذج</th>
                        <th class="px-4 py-2.5 text-right text-gray-400 text-xs">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr class="border-t border-gray-100 hover:bg-amber-50 transition">
                            <td class="px-4 py-2.5 text-gray-700">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $actionColors = ['create' => 'text-green-700', 'update' => 'text-blue-700', 'delete' => 'text-red-700', 'login' => 'text-amber-600', 'logout' => 'text-gray-500'];
                                    $actionLabels = ['create' => 'إنشاء', 'update' => 'تحديث', 'delete' => 'حذف', 'login' => 'دخول', 'logout' => 'خروج'];
                                @endphp
                                <span class="{{ $actionColors[$log->action] ?? 'text-gray-500' }} font-medium">{{ $actionLabels[$log->action] ?? $log->action }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 text-xs">{{ class_basename($log->model_type) }}</td>
                            <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $log->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">لا توجد سجلات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- What We Did (Development Notes) --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-amber-600 font-bold mb-4 text-sm uppercase tracking-wider">📝 وش نسوي</h2>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
            <ul class="space-y-3 text-gray-700 text-sm">
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">القضايا الشهرية:</strong> جدول + طباعة + تصفية حية</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">دليل الاستخدام:</strong> صفحة تعليمات للمستخدمين الجدد</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">إشعارات فخمة:</strong> توست + صوت عند وصول الإشعارات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">صوت رسائل الشات:</strong> بيب عند وصول رسالة من شخص ثاني</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">حماية البريد الإلكتروني:</strong> اعتراض Cloudflare email + توست + صوت</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">النسخ الاحتياطي التلقائي:</strong> يومي (3AM) + كل 30 دقيقة عند التغيير</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">إزالة صلاحيات الأدوار:</strong> كل أعضاء الفريق يشوفون كل البيانات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">الموارد البشرية:</strong> موظفين، إجازات، موافقة/رفض</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">المالية:</strong> معاملات، فواتير، رسوم، طباعة, تصدير</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">تصدير Excel:</strong> تقارير بتصميم ذهبي + تصدير شامل + صفحة ملخص</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">الشات:</strong> محادثات خاصة، رفع ملفات، أصوات، إشعارات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">الوضع النهاري:</strong> إعادة تصميم بخلفية بيج وكروت بيضاء</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">CSP:</strong> حماية بـ nonce لكل السكربتات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-600 mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">إدارة المستخدمين:</strong> فتح صلاحية lawyer و staff</span>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
