@extends('layouts.app')

@section('title', 'لوحة المطور')

@section('content')
<div class="space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gold">⚙️ لوحة المطور</h1>
        <div class="flex items-center gap-2 text-xs text-ivory/40">
            <span>v{{ $laravelVersion }}</span>
            <span class="w-1 h-1 rounded-full bg-ivory/20"></span>
            <span>PHP {{ $phpVersion }}</span>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 px-5 py-3 rounded-xl text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-5 py-3 rounded-xl text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- System Info Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4">
            <p class="text-ivory/40 text-xs mb-1">PHP</p>
            <p class="text-lg font-bold text-ivory">{{ $phpVersion }}</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4">
            <p class="text-ivory/40 text-xs mb-1">Laravel</p>
            <p class="text-lg font-bold text-ivory">{{ $laravelVersion }}</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4">
            <p class="text-ivory/40 text-xs mb-1">قاعدة البيانات</p>
            <p class="text-lg font-bold text-ivory truncate">{{ $dbName }}</p>
            <p class="text-ivory/30 text-xs">{{ $dbSize }} MB</p>
        </div>
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4">
            <p class="text-ivory/40 text-xs mb-1">المستخدمين</p>
            <p class="text-lg font-bold text-ivory">{{ $userCount }}</p>
            <p class="text-ivory/30 text-xs">{{ $logCount }} سجل</p>
        </div>
    </div>

    {{-- Storage --}}
    <div class="bg-navy-light rounded-xl border border-ivory/10 p-4">
        <div class="flex items-center justify-between">
            <p class="text-ivory/40 text-xs">المساحة التخزينية</p>
            <p class="text-sm text-ivory/60">{{ $diskFree }} GB / {{ $diskTotal }} GB</p>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-navy-light rounded-xl border border-ivory/10 p-5">
        <h2 class="text-gold font-bold mb-4 text-sm uppercase tracking-wider">⚡ إجراءات سريعة</h2>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('developer.cache-clear') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-500/20 transition">🧹 مسح الكاش</button>
            </form>
            <form action="{{ route('developer.cache-all') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-gold/10 border border-gold/30 text-gold px-4 py-2 rounded-lg text-sm font-medium hover:bg-gold/20 transition">⚡ تخزين الكاش</button>
            </form>
            <form action="{{ route('developer.optimize') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-blue-500/10 border border-blue-500/30 text-blue-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-500/20 transition">✨ تحسين</button>
            </form>
            <form action="{{ route('developer.migrate') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-purple-500/10 border border-purple-500/30 text-purple-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-500/20 transition">📦 ترحيل</button>
            </form>
            <form action="{{ route('developer.storage-link') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-500/20 transition">🔗 رابط التخزين</button>
            </form>
        </div>
    </div>

    {{-- Cache Status --}}
    <div class="bg-navy-light rounded-xl border border-ivory/10 p-5">
        <h2 class="text-gold font-bold mb-4 text-sm uppercase tracking-wider">💾 حالة الكاش</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($cacheDrivers as $key => $cached)
                <div class="flex items-center gap-3 px-4 py-3 rounded-xl {{ $cached ? 'bg-green-500/10' : 'bg-red-500/10' }}">
                    <span class="w-2.5 h-2.5 rounded-full {{ $cached ? 'bg-green-400' : 'bg-red-400' }}"></span>
                    <span class="text-sm text-ivory/70">{{ $key }}</span>
                    <span class="text-xs {{ $cached ? 'text-green-400' : 'text-red-400' }}">{{ $cached ? 'مخزن' : 'غير مخزن' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Recent Audit Log --}}
    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-ivory/5 flex items-center justify-between">
            <h2 class="text-gold font-bold text-sm uppercase tracking-wider">📋 آخر النشاطات</h2>
            <a href="{{ route('audit-log.index') }}" class="text-ivory/40 text-xs hover:text-gold transition">عرض الكل</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-white/5">
                        <th class="px-4 py-2.5 text-right text-ivory/40 text-xs">المستخدم</th>
                        <th class="px-4 py-2.5 text-right text-ivory/40 text-xs">الإجراء</th>
                        <th class="px-4 py-2.5 text-right text-ivory/40 text-xs">النموذج</th>
                        <th class="px-4 py-2.5 text-right text-ivory/40 text-xs">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr class="border-t border-ivory/5 hover:bg-gold/5 transition">
                            <td class="px-4 py-2.5 text-ivory/70">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $actionColors = ['create' => 'text-green-400', 'update' => 'text-blue-400', 'delete' => 'text-red-400', 'login' => 'text-gold', 'logout' => 'text-ivory/50'];
                                    $actionLabels = ['create' => 'إنشاء', 'update' => 'تحديث', 'delete' => 'حذف', 'login' => 'دخول', 'logout' => 'خروج'];
                                @endphp
                                <span class="{{ $actionColors[$log->action] ?? 'text-ivory/50' }} font-medium">{{ $actionLabels[$log->action] ?? $log->action }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-ivory/50 text-xs">{{ class_basename($log->model_type) }}</td>
                            <td class="px-4 py-2.5 text-ivory/30 text-xs">{{ $log->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-ivory/30">لا توجد سجلات</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- What We Did (Development Notes) --}}
    <div class="bg-navy-light rounded-xl border border-ivory/10 p-5">
        <h2 class="text-gold font-bold mb-4 text-sm uppercase tracking-wider">📝 وش نسوي</h2>
        <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
            <ul class="space-y-3 text-ivory/70 text-sm">
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">القضايا الشهرية:</strong> جدول + طباعة + تصفية حية</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">دليل الاستخدام:</strong> صفحة تعليمات للمستخدمين الجدد</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">إشعارات فخمة:</strong> توست + صوت عند وصول الإشعارات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">صوت رسائل الشات:</strong> بيب عند وصول رسالة من شخص ثاني</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">حماية البريد الإلكتروني:</strong> اعتراض Cloudflare email + توست + صوت</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">النسخ الاحتياطي التلقائي:</strong> يومي (3AM) + كل 30 دقيقة عند التغيير</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">إزالة صلاحيات الأدوار:</strong> كل أعضاء الفريق يشوفون كل البيانات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">الموارد البشرية:</strong> موظفين، إجازات، موافقة/رفض</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">المالية:</strong> معاملات، فواتير، رسوم، طباعة, تصدير</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">تصدير Excel:</strong> تقارير بتصميم ذهبي + تصدير شامل + صفحة ملخص</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">الشات:</strong> محادثات خاصة، رفع ملفات، أصوات، إشعارات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">الوضع النهاري:</strong> إعادة تصميم بخلفية بيج وكروت بيضاء</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">CSP:</strong> حماية بـ nonce لكل السكربتات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold mt-0.5">🟢</span>
                    <span><strong class="text-ivory">إدارة المستخدمين:</strong> فتح صلاحية lawyer و staff</span>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
