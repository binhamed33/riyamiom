@extends('layouts.app')

@section('title', 'لوحة المطور')

@section('content')
<div class="space-y-6" dir="rtl">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gold-dark">⚙️ لوحة المطور</h1>
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
        <h2 class="text-gold-dark font-bold mb-4 text-sm uppercase tracking-wider">⚡ إجراءات سريعة</h2>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('developer.cache-clear') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-red-100 border border-red-200 text-red-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition">🧹 مسح الكاش</button>
            </form>
            <form action="{{ route('developer.cache-all') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-gold/12 border border-gold/15 text-gold-dark px-4 py-2 rounded-lg text-sm font-medium hover:bg-gold/15 transition">⚡ تخزين الكاش</button>
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
            <a href="{{ route('developer.features') }}" class="bg-indigo-100 border border-indigo-200 text-indigo-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-200 inline-block transition">🔘 إدارة الميزات</a>
            <a href="{{ route('developer.subscription.config') }}" class="bg-gold/12 border border-gold/15 text-gold-dark px-4 py-2 rounded-lg text-sm font-medium hover:bg-gold/15 inline-block transition">🛡️ Subscription Configuration</a>
            <form action="{{ route('developer.automation.toggle') }}" method="POST" class="inline">
                @csrf
                @php $autoOn = \App\Models\Setting::get('automation_enabled', '0') === '1'; @endphp
                <button type="submit" class="{{ $autoOn ? 'bg-green-100 border-green-200 text-green-700 hover:bg-green-200' : 'bg-gray-100 border-gray-200 text-gray-600 hover:bg-gray-200' }} border px-4 py-2 rounded-lg text-sm font-medium transition">
                    ⚙️ الأتمتة: {{ $autoOn ? 'مفعّلة' : 'معطّلة' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Cache Status --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-gold-dark font-bold mb-4 text-sm uppercase tracking-wider">💾 حالة الكاش</h2>
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
            <h2 class="text-gold-dark font-bold text-sm uppercase tracking-wider">📋 آخر النشاطات</h2>
            <a href="{{ route('audit-log.index') }}" class="text-gray-400 text-xs hover:text-gold-dark transition">عرض الكل</a>
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
                        <tr class="border-t border-gray-100 hover:bg-gold/10 transition">
                            <td class="px-4 py-2.5 text-gray-700">{{ $log->user?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $actionColors = ['create' => 'text-green-700', 'update' => 'text-blue-700', 'delete' => 'text-red-700', 'login' => 'text-gold-dark', 'logout' => 'text-gray-500'];
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

    {{-- Suggestions --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-gold-dark font-bold text-sm uppercase tracking-wider">💡 اقتراحات الموظفين</h2>
            <span class="text-xs text-gray-400">{{ $suggestions->count() }} اقتراح</span>
        </div>

        @forelse($suggestions as $suggestion)
            <div class="border border-gray-100 rounded-xl p-4 mb-4" x-data="{ editing: false }">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-gold-light to-gold-dark flex items-center justify-center flex-shrink-0 text-white text-sm font-bold">
                        {{ mb_substr($suggestion->user->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900">
                            {{ $suggestion->user->name }}
                            <span class="text-[11px] font-mono text-gray-400">#{{ $suggestion->user_id }}</span>
                        </p>
                        <p class="text-[11px] text-gray-400">
                            {{ \App\Support\SuggestionContext::roleLabel($suggestion->context['user']['role'] ?? $suggestion->user->role) }}
                            • {{ $suggestion->created_at->format('Y-m-d H:i') }}
                            • {{ $suggestion->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('suggestions.status', $suggestion) }}" class="inline">
                            @csrf
                            <input type="hidden" name="status" value="implemented">
                            <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full border transition {{ $suggestion->status === 'implemented' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-green-700 border-green-300 hover:bg-green-50' }}" title="تم التنفيذ">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                منفّذ
                            </button>
                        </form>
                        <form method="POST" action="{{ route('suggestions.status', $suggestion) }}" class="inline">
                            @csrf
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full border transition {{ $suggestion->status === 'pending' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gold-dark border-gold/25 hover:bg-gold/10' }}" title="قيد الدراسة أو التنفيذ">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                قيد الدراسة
                            </button>
                        </form>
                    </div>
                </div>

                @if($suggestion->title)
                    <p class="text-sm font-bold text-gold-dark mb-2">{{ $suggestion->title }}</p>
                @endif

                <p x-show="!editing" class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap bg-gray-50 border border-gray-100 rounded-xl p-3">{{ $suggestion->content }}</p>

                {{-- سياق الإرسال: يُقرأ من اللقطة المحفوظة لا من الحالة الراهنة --}}
                @php $ctx = $suggestion->context ?? []; @endphp
                @if($ctx)
                    <dl class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-2 text-[11px] bg-gold/5 border border-gold/15 rounded-xl p-3">
                        <div class="min-w-0">
                            <dt class="text-gray-400">المكتب</dt>
                            <dd class="font-semibold text-gray-700 truncate">{{ $ctx['office']['name'] ?? '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-gray-400">النطاق</dt>
                            <dd class="font-mono text-gray-700 truncate" dir="ltr">{{ $ctx['office']['domain'] ?? '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-gray-400">البريد</dt>
                            <dd class="text-gray-700 truncate" dir="ltr">{{ $ctx['user']['email'] ?? $suggestion->user->email }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-gray-400">الصفحة</dt>
                            <dd class="font-mono text-gray-700 truncate" dir="ltr">{{ $ctx['origin']['page'] ?? '—' }}</dd>
                        </div>
                        @if(!empty($ctx['device']))
                            <div class="min-w-0 col-span-2">
                                <dt class="text-gray-400">الجهاز</dt>
                                <dd class="text-gray-700 truncate">{{ implode(' · ', array_filter([$ctx['device']['type'] ?? null, $ctx['device']['platform'] ?? null, $ctx['device']['browser'] ?? null])) }}</dd>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <dt class="text-gray-400">رقم الاقتراح</dt>
                            <dd class="font-mono text-gray-700">#{{ $suggestion->id }}</dd>
                        </div>
                    </dl>
                @endif

                <form x-show="editing" x-cloak method="POST" action="{{ route('suggestions.update', $suggestion) }}" class="mt-1">
                    @csrf
                    @method('PUT')
                    <textarea name="content" rows="3" minlength="20" maxlength="2000" class="w-full bg-gray-50 border border-gold/25 rounded-xl px-4 py-2.5 text-sm text-gray-900 focus:border-gold-dark transition resize-y">{{ $suggestion->content }}</textarea>
                    <div class="flex gap-2 mt-2">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold px-4 py-1.5 rounded-lg text-xs transition">حفظ التعديل</button>
                        <button type="button" @click="editing = false" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-1.5 rounded-lg text-xs transition">إلغاء</button>
                    </div>
                </form>

                <div class="flex items-center justify-between mt-2">
                    <p class="text-[11px] text-gray-400">
                        @if($suggestion->replied_at)
                            آخر رد: {{ $suggestion->replied_at->diffForHumans() }}
                        @else
                            لم يُرد بعد
                        @endif
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="editing = !editing" class="text-[11px] text-blue-700 bg-blue-100 hover:bg-blue-200 px-3 py-1 rounded-lg transition font-medium">تعديل</button>
                        <form method="POST" action="{{ route('suggestions.destroy', $suggestion) }}" @submit.prevent="if (confirm('حذف هذا الاقتراح؟ يختفي من القائمة ويبقى محفوظاً في القاعدة.')) $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-[11px] text-red-700 bg-red-100 hover:bg-red-200 px-3 py-1 rounded-lg transition font-medium">حذف</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('suggestions.reply', $suggestion) }}" class="mt-3">
                    @csrf
                    <textarea name="reply" rows="2" placeholder="اكتب ردّك لصاحب الاقتراح..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-gold/25 focus:bg-gold/10 transition resize-y">{{ old('reply', $suggestion->developer_reply) }}</textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                            إرسال الرد
                        </button>
                    </div>
                </form>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-200 p-10 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-gold/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                </div>
                <p class="text-sm font-bold text-gray-700">لا توجد اقتراحات بعد</p>
                <p class="text-xs text-gray-400 mt-1.5">اقتراحات فريق المكتب ستظهر هنا فور إرسالها.</p>
            </div>
        @endforelse
    </div>

    {{-- Announcement of the Day --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-gold-dark font-bold text-sm uppercase tracking-wider">📢 رسالة اليوم</h2>
        </div>

        @if($currentAnnouncement)
            <div class="bg-gold/10 border border-gold/15 rounded-xl p-4 mb-4">
                <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">{{ $currentAnnouncement->content }}</p>
                <p class="text-[11px] text-gray-400 mt-2">
                    نُشرت {{ $currentAnnouncement->created_at->diffForHumans() }} • شاهدها {{ $currentAnnouncement->reads_count }} مستخدم حتى الآن
                </p>
            </div>
        @else
            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 mb-4 text-center">
                <p class="text-sm text-gray-400">لا توجد رسالة حالية — انشر أول رسالة اليوم</p>
            </div>
        @endif

        <p class="text-xs text-gray-500 mb-2">اكتب رسالة جديدة لتعريف المستخدمين بالإضافات الجديدة — ستظهر لكل مستخدم مرة واحدة فقط، ولن تظهر مجدداً حتى تكتب رسالة جديدة.</p>

        <form method="POST" action="{{ route('announcements.publish') }}">
            @csrf
            <textarea name="content" rows="3" minlength="5" maxlength="2000" placeholder="اكتب رسالة اليوم..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-gold/25 focus:bg-gold/10 transition resize-y">{{ $currentAnnouncement ? '' : '' }}</textarea>
            <div class="flex justify-end mt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    نشر رسالة اليوم
                </button>
            </div>
        </form>
    </div>

    {{-- What We Did (Development Notes) --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-gold-dark font-bold mb-4 text-sm uppercase tracking-wider">📝 وش نسوي</h2>
        <div class="bg-white rounded-xl p-5 border border-gray-100">
            <ul class="space-y-3 text-gray-700 text-sm">
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">القضايا الشهرية:</strong> جدول + طباعة + تصفية حية</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">دليل الاستخدام:</strong> صفحة تعليمات للمستخدمين الجدد</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">إشعارات فخمة:</strong> توست + صوت عند وصول الإشعارات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">صوت رسائل الشات:</strong> بيب عند وصول رسالة من شخص ثاني</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">حماية البريد الإلكتروني:</strong> اعتراض Cloudflare email + توست + صوت</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">النسخ الاحتياطي التلقائي:</strong> يومي (3AM) + كل 30 دقيقة عند التغيير</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">إزالة صلاحيات الأدوار:</strong> كل أعضاء الفريق يشوفون كل البيانات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">الموارد البشرية:</strong> موظفين، إجازات، موافقة/رفض</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">المالية:</strong> معاملات، فواتير، رسوم، طباعة, تصدير</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">تصدير Excel:</strong> تقارير بتصميم ذهبي + تصدير شامل + صفحة ملخص</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">الشات:</strong> محادثات خاصة، رفع ملفات، أصوات، إشعارات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">الوضع النهاري:</strong> إعادة تصميم بخلفية بيج وكروت بيضاء</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">CSP:</strong> حماية بـ nonce لكل السكربتات</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-gold-dark mt-0.5">🟢</span>
                    <span><strong class="text-gray-700">إدارة المستخدمين:</strong> فتح صلاحية lawyer و staff</span>
                </li>
            </ul>
        </div>
    </div>
</div>
@endsection
