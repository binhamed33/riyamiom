@extends('layouts.marketing')

@section('title', 'التسجيل')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">التسجيل</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">ابدأ تجربتك المجانية — <span class="gold-text">14 يومًا</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                بدون بطاقة ائتمان، وبدون أي التزام. تفعيل الحساب خلال ٥ دقائق،
                وتصلك بيانات الدخول بالبريد بعد موافقة الإدارة خلال ٢٤–٤٨ ساعة.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20">
        <div class="grid gap-5 md:grid-cols-3">
            <div class="card-lux rounded-2xl p-7">
                <span class="font-latin text-2xl font-bold text-gold/60">01</span>
                <h2 class="mt-4 text-lg font-bold">أرسل طلبك</h2>
                <p class="mt-2.5 text-sm leading-8 text-muted">سجّل بيانات مكتبك من خلال صفحة التواصل — نراجع طلبك ونؤكد قبوله بالتفصيل.</p>
            </div>
            <div class="card-lux rounded-2xl p-7">
                <span class="font-latin text-2xl font-bold text-gold/60">02</span>
                <h2 class="mt-4 text-lg font-bold">مراجعة وتفعيل</h2>
                <p class="mt-2.5 text-sm leading-8 text-muted">تستلم موافقة الإدارة خلال ٢٤–٤٨ ساعة مع بيانات الدخول بالبريد الإلكتروني.</p>
            </div>
            <div class="card-lux rounded-2xl p-7">
                <span class="font-latin text-2xl font-bold text-gold/60">03</span>
                <h2 class="mt-4 text-lg font-bold">ابدأ العمل</h2>
                <p class="mt-2.5 text-sm leading-8 text-muted">سجّل قضاياك وأضف فريقك — وفريق الدعم معك خطوة بخطوة طوال التجربة.</p>
            </div>
        </div>

        <div class="mt-12 rounded-2xl border border-gold/20 bg-gold/5 p-8 text-center">
            <h2 class="text-xl font-bold">جاهز للبدء؟</h2>
            <p class="mt-2 text-sm text-muted">تواصل معنا وسنفعّل لك الحساب خلال ٢٤–٤٨ ساعة.</p>
            <div class="mt-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ route('marketing.contact') }}" class="btn-gold rounded-full px-8 py-3.5 text-sm font-semibold">تواصل معنا للتسجيل</a>
                <a href="{{ config('marketing.portfolio_url') }}" class="btn-ghost rounded-full px-8 py-3.5 text-sm">العودة للرئيسية</a>
            </div>
        </div>
    </section>
@endsection
