@extends('layouts.marketing')

@section('title', 'تواصل معنا')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">التواصل</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">تواصل مع <span class="gold-text">فريقنا</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                للتسجيل أو الاستفسار أو طلب عرض توضيحي — نرد خلال ساعات العمل.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card-lux rounded-2xl p-7 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-gold/25 bg-gold/10">
                    <svg class="h-5 w-5 text-gold-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                </span>
                <h2 class="mt-4 text-sm font-bold">الهاتف</h2>
                <p class="font-latin mt-2 text-sm text-muted" dir="ltr">{{ config('marketing.phone') }}</p>
            </div>
            <div class="card-lux rounded-2xl p-7 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-gold/25 bg-gold/10">
                    <svg class="h-5 w-5 text-gold-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                </span>
                <h2 class="mt-4 text-sm font-bold">البريد الإلكتروني</h2>
                <p class="font-latin mt-2 break-all text-sm text-muted" dir="ltr">{{ config('marketing.email') }}</p>
            </div>
            <div class="card-lux rounded-2xl p-7 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-gold/25 bg-gold/10">
                    <svg class="h-5 w-5 text-gold-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                </span>
                <h2 class="mt-4 text-sm font-bold">العنوان</h2>
                <p class="mt-2 text-sm leading-7 text-muted">{{ config('marketing.address') }}</p>
            </div>
            <div class="card-lux rounded-2xl p-7 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-gold/25 bg-gold/10">
                    <svg class="h-5 w-5 text-gold-soft" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h2 class="mt-4 text-sm font-bold">ساعات العمل</h2>
                <p class="mt-2 text-sm leading-7 text-muted">{{ config('marketing.hours') }}</p>
            </div>
        </div>

        <div class="mt-12 rounded-2xl border border-gold/20 bg-gold/5 p-8 text-center">
            <h2 class="text-xl font-bold">أرسل لنا رسالة</h2>
            <p class="mt-2 text-sm text-muted">راسلنا على البريد الإلكتروني {{ config('marketing.email') }} وسنرد عليك بأسرع وقت.</p>
            <a href="mailto:{{ config('marketing.email') }}?subject={{ urlencode('استفسار عن LexPro') }}" class="btn-gold mt-6 inline-block rounded-full px-8 py-3.5 text-sm font-semibold">راسلنا الآن</a>
        </div>
    </section>
@endsection
