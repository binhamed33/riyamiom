@extends('layouts.marketing')

@section('title', 'الدليل')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">الدليل</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">كيف تبدأ مع <span class="gold-text">LexPro</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                من التسجيل إلى تشغيل مكتبك كاملًا في خطوات بسيطة — لا تحتاج خبرة تقنية.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20">
        <div class="flex flex-col gap-4">
            @php
                $steps = [
                    ['t' => 'التسجيل بالحساب', 'd' => 'أرسل طلبك من صفحة التواصل، وتصلك بيانات الدخول بالبريد خلال ٢٤–٤٨ ساعة.'],
                    ['t' => 'إعداد المكتب', 'd' => 'أدخل بيانات مكتبك وأضف زملاءك من صفحة الفريق مع تحديد الأدوار والصلاحيات.'],
                    ['t' => 'تسجيل القضايا', 'd' => 'سجّل قضاياك مع عملائك، وارفع المستندات، وحدد مواعيد الجلسات.'],
                    ['t' => 'المتابعة اليومية', 'd' => 'لوحة التحكم تعرض القضايا النشطة والجلسات القادمة والمهام المتأخرة بلمحة واحدة.'],
                    ['t' => 'التقارير والفوترة', 'd' => 'أصدر تقارير الحالات والإحصائيات وتابع حدود باقاتك من صفحة الإعدادات.'],
                ];
            @endphp
            @foreach ($steps as $i => $s)
                <div class="card-lux rounded-2xl p-6 flex items-start gap-5">
                    <span class="font-latin flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gold/30 bg-gold/10 font-bold text-gold-soft">{{ $i + 1 }}</span>
                    <div>
                        <h2 class="text-base font-bold">{{ $s['t'] }}</h2>
                        <p class="mt-1.5 text-sm leading-8 text-muted">{{ $s['d'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-12 rounded-2xl border border-gold/20 bg-gold/5 p-8 text-center">
            <h2 class="text-xl font-bold">دليل النظام الكامل</h2>
            <p class="mt-2 text-sm text-muted">
                @auth
                    افتح الدليل التفصيلي داخل النظام لاستعراض كل وحدة بالتفصيل.
                @else
                    الدليل التفصيلي متاح داخل النظام بعد تسجيل الدخول.
                @endauth
            </p>
            <div class="mt-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
                @auth
                    <a href="{{ route('guide') }}" class="btn-gold rounded-full px-8 py-3.5 text-sm font-semibold">افتح دليل النظام</a>
                @else
                    <a href="{{ route('marketing.register') }}" class="btn-gold rounded-full px-8 py-3.5 text-sm font-semibold">ابدأ تجربتك المجانية</a>
                @endauth
            </div>
        </div>
    </section>
@endsection
