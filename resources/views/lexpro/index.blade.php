@extends('layouts.app')

@section('title', 'LexPro - نظام إدارة المكاتب القانونية')

@section('content')
<div class="max-w-4xl mx-auto space-y-8" dir="rtl">

    {{-- Hero --}}
    <div class="text-center py-12">
        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-gold to-gold/60 flex items-center justify-center mx-auto mb-6 shadow-lg shadow-gold/20">
            <span class="text-3xl font-bold text-navy-darkest">LP</span>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold text-gold font-heading mb-4">LexPro</h1>
        <p class="text-ivory/60 text-xl">نظام متكامل لإدارة المكاتب القانونية والمحاماة</p>
    </div>

    {{-- What is LexPro --}}
    <div class="bg-navy-light rounded-2xl border border-ivory/10 p-8">
        <h2 class="text-2xl font-bold text-gold mb-6">ما هو LexPro؟</h2>
        <p class="text-ivory/80 leading-relaxed text-lg">
            <strong class="text-gold">LexPro</strong> هو نظام إلكتروني متكامل لإدارة المكاتب القانونية، تم تطويره خصيصاً<br class="hidden md:block">
            ليسهل على المحامين وإدارة المكتب إدارة القضايا والعملاء والجلسات والمهام اليومية.
        </p>
    </div>

    {{-- Features --}}
    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-navy-light rounded-2xl border border-ivory/10 p-6">
            <div class="w-12 h-12 rounded-xl bg-gold/15 flex items-center justify-center mb-4 text-gold text-xl">📁</div>
            <h3 class="text-lg font-bold text-ivory mb-2">إدارة القضايا</h3>
            <p class="text-ivory/60 text-sm leading-relaxed">
                تسجيل القضايا مع رقم القضية، المحكمة، الموضوع، الخصم، الحالة، والقسم.
                إمكانية إرفاق المستندات والملفات لكل قضية.
            </p>
        </div>
        <div class="bg-navy-light rounded-2xl border border-ivory/10 p-6">
            <div class="w-12 h-12 rounded-xl bg-gold/15 flex items-center justify-center mb-4 text-gold text-xl">👥</div>
            <h3 class="text-lg font-bold text-ivory mb-2">إدارة العملاء</h3>
            <p class="text-ivory/60 text-sm leading-relaxed">
                قاعدة بيانات متكاملة للعملاء تشمل الاسم، الهاتف، البريد، والعنوان.
                ربط العملاء بقضاياهم وجلساتهم.
            </p>
        </div>
        <div class="bg-navy-light rounded-2xl border border-ivory/10 p-6">
            <div class="w-12 h-12 rounded-xl bg-gold/15 flex items-center justify-center mb-4 text-gold text-xl">📅</div>
            <h3 class="text-lg font-bold text-ivory mb-2">الجلسات القضائية</h3>
            <p class="text-ivory/60 text-sm leading-relaxed">
                جدولة جلسات المحكمة وتصنيفها حسب التاريخ والنوع.
                عرض جلسات اليوم مباشرة مع إشعارات التذكير.
            </p>
        </div>
        <div class="bg-navy-light rounded-2xl border border-ivory/10 p-6">
            <div class="w-12 h-12 rounded-xl bg-gold/15 flex items-center justify-center mb-4 text-gold text-xl">✅</div>
            <h3 class="text-lg font-bold text-ivory mb-2">المهام</h3>
            <p class="text-ivory/60 text-sm leading-relaxed">
                نظام مهام متكامل مع تحديد الأولويات (عادية، مهمة، عاجلة)
                وتوزيع المهام على أعضاء الفريق.
            </p>
        </div>
        <div class="bg-navy-light rounded-2xl border border-ivory/10 p-6">
            <div class="w-12 h-12 rounded-xl bg-gold/15 flex items-center justify-center mb-4 text-gold text-xl">💬</div>
            <h3 class="text-lg font-bold text-ivory mb-2">المحادثات الداخلية</h3>
            <p class="text-ivory/60 text-sm leading-relaxed">
                نظام تواصل فوري بين أعضاء الفريق مع إمكانية إرفاق الملفات
                والصور، وإشعارات فورية.
            </p>
        </div>
        <div class="bg-navy-light rounded-2xl border border-ivory/10 p-6">
            <div class="w-12 h-12 rounded-xl bg-gold/15 flex items-center justify-center mb-4 text-gold text-xl">💰</div>
            <h3 class="text-lg font-bold text-ivory mb-2">المالية</h3>
            <p class="text-ivory/60 text-sm leading-relaxed">
                إدارة المعاملات المالية، الفواتير، والرسوم.
                طباعة وتصدير التقارير المالية.
            </p>
        </div>
    </div>

    {{-- More Features --}}
    <div class="bg-navy-light rounded-2xl border border-ivory/10 p-8">
        <h2 class="text-xl font-bold text-gold mb-6">مميزات إضافية</h2>
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="text-center p-4">
                <span class="text-2xl">👔</span>
                <p class="text-ivory/70 font-medium mt-2">الموارد البشرية</p>
                <p class="text-ivory/50 text-xs mt-1">إدارة الموظفين والإجازات</p>
            </div>
            <div class="text-center p-4">
                <span class="text-2xl">📊</span>
                <p class="text-ivory/70 font-medium mt-2">التقارير والتصدير</p>
                <p class="text-ivory/50 text-xs mt-1">Excel بتصميم احترافي</p>
            </div>
            <div class="text-center p-4">
                <span class="text-2xl">📄</span>
                <p class="text-ivory/70 font-medium mt-2">المستندات</p>
                <p class="text-ivory/50 text-xs mt-1">مستودع رقمي للملفات</p>
            </div>
            <div class="text-center p-4">
                <span class="text-2xl">🔐</span>
                <p class="text-ivory/70 font-medium mt-2">حماية وأمان</p>
                <p class="text-ivory/50 text-xs mt-1">تشفير البيانات + CSP</p>
            </div>
            <div class="text-center p-4">
                <span class="text-2xl">🌙</span>
                <p class="text-ivory/70 font-medium mt-2">وضعين للعرض</p>
                <p class="text-ivory/50 text-xs mt-1">ليلي ونهاري</p>
            </div>
            <div class="text-center p-4">
                <span class="text-2xl">📱</span>
                <p class="text-ivory/70 font-medium mt-2">متوافق مع الجوال</p>
                <p class="text-ivory/50 text-xs mt-1">تصفح من أي جهاز</p>
            </div>
        </div>
    </div>

    {{-- Tech Stack --}}
    <div class="bg-navy-light rounded-2xl border border-ivory/10 p-8 text-center">
        <h2 class="text-xl font-bold text-gold mb-4">التقنيات المستخدمة</h2>
        <div class="flex flex-wrap justify-center gap-3">
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">Laravel</span>
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">PHP</span>
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">MySQL</span>
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">Alpine.js</span>
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">Tailwind CSS</span>
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">Nginx</span>
            <span class="px-4 py-2 rounded-xl bg-white/5 border border-ivory/10 text-ivory/70 text-sm">Cloudflare</span>
        </div>
    </div>

    {{-- Footer --}}
    <div class="text-center py-8 text-ivory/30 text-sm border-t border-ivory/5">
        <p>LexPro — نظام إدارة المكاتب القانونية</p>
    </div>
</div>
@endsection
