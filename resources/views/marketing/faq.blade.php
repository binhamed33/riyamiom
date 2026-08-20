@extends('layouts.marketing')

@section('title', 'الأسئلة الشائعة')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">الأسئلة الشائعة</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">إجابات واضحة، <span class="gold-text">قبل أن تسأل.</span></h1>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 pb-20">
        <div class="flex flex-col gap-3">
            @php
                $faqs = [
                    ['q' => 'هل التجربة المجانية تتطلب بطاقة ائتمان؟', 'a' => 'لا إطلاقًا — التجربة المجانية ١٤ يومًا كاملة بدون بطاقة ائتمان ولا أي التزام مالي، وتفعيل الحساب خلال ٥ دقائق فقط من التسجيل.'],
                    ['q' => 'ما مدى أمان بيانات مكتبنا على مُداوَلة؟', 'a' => 'بياناتك مشفّرة أثناء النقل والتخزين، مع فصل كامل بين بيانات كل مكتب، صلاحيات دقيقة حسب الأدوار، سجل تدقيق لكل عملية، وتحقق ثنائي MFA للمستخدمين — مع نسخ احتياطي يومي مشفّر.'],
                    ['q' => 'هل يمكن ترحيل بياناتنا من نظام آخر؟', 'a' => 'نعم، فريقنا يساعدك على ترحيل بياناتك من أي نظام أو جداول Excel — بخطة ترحيل خطوة بخطوة مع التحقق من صحة البيانات قبل التشغيل الكامل.'],
                    ['q' => 'هل أحتاج إلى أجهزة أو بنية تحتية خاصة؟', 'a' => 'لا — مُداوَلة خدمة سحابية بالكامل تعمل من أي متصفح على الحاسوب أو الجوال، ولا تحتاج تثبيت برامج أو صيانة. التحديثات والنسخ الاحتياطي تتم تلقائيًا.'],
                    ['q' => 'هل يقدمون دعمًا فنيًا خلال فترة التجربة؟', 'a' => 'نعم، الدعم متاح منذ اليوم الأول عبر البريد والهاتف، مع دليل استخدام عربي شامل ودورات تهيئة قصيرة لفريق مكتبك.'],
                    ['q' => 'ماذا يحدث بعد انتهاء التجربة؟', 'a' => 'تختار الباقة المناسبة لمكتبك، أو توقّف بدون أي رسوم. كل البيانات التي أدخلتها تبقى محفوظة وآمنة في حسابك.'],
                ];
            @endphp
            @foreach ($faqs as $i => $f)
                <details class="group rounded-2xl border border-ivory/10 bg-white/[0.015] transition-colors open:border-gold/35 open:bg-gold/5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-5 text-sm font-semibold">
                        {{ $f['q'] }}
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gold/25 bg-gold/10 text-lg text-gold-soft transition-transform duration-500 group-open:rotate-45">+</span>
                    </summary>
                    <div class="px-6 pb-6">
                        <p class="text-sm leading-8 text-muted">{{ $f['a'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>

        <p class="mt-10 text-center text-sm text-muted">
            سؤال آخر؟
            <a href="{{ route('marketing.contact') }}" class="text-gold-soft underline decoration-gold/40 underline-offset-4 transition-colors hover:text-gold">تواصل معنا</a>
            — نرد خلال ساعات العمل.
        </p>
    </section>
@endsection
