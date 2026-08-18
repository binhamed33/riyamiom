@extends('layouts.marketing')

@section('title', 'الأسعار')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">الأسعار</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">اختر الحل المناسب <span class="gold-text">لمكتبك</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                14 يومًا تجربة مجانية على أي باقة — دون بطاقة ائتمان.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20">
        @php
            $plans = [
                [
                    'name' => 'الأساسية',
                    'price' => 29,
                    'per' => 'شهريًا',
                    'cta' => 'ابدأ التجربة',
                    'featured' => false,
                    'limits' => [['المستخدمون', '١'], ['القضايا', '٢٠'], ['المستندات', '١٠٠'], ['التخزين', '١ ج.ب']],
                    'features' => ['لوحة تحكم وقضايا وعملاء', 'جلسات ومهام', 'فريق حتى مستخدم واحد', 'دعم عبر البريد'],
                ],
                [
                    'name' => 'الاحترافية',
                    'price' => 79,
                    'per' => 'شهريًا',
                    'cta' => 'ابدأ التجربة',
                    'featured' => true,
                    'tag' => 'الأكثر طلبًا',
                    'limits' => [['المستخدمون', '١٠'], ['القضايا', 'غير محدود'], ['المستندات', 'غير محدود'], ['التخزين', '١٠ ج.ب']],
                    'features' => ['كل مزايا الأساسية', 'مستندات بذكاء اصطناعي', 'سجل تدقيق كامل', 'تقارير وتصدير', 'دعم أولوية'],
                ],
                [
                    'name' => 'الشركات',
                    'price' => 199,
                    'per' => 'شهريًا',
                    'cta' => 'تواصل معنا',
                    'featured' => false,
                    'billingNote' => 'باقة مخصصة — تواصل معنا',
                    'limits' => [['المستخدمون', 'غير محدود'], ['القضايا', 'غير محدود'], ['المستندات', 'غير محدود'], ['التخزين', 'مخصص']],
                    'features' => ['كل مزايا الاحترافية', 'صلاحيات متقدمة', 'دعم مخصص وإعداد ميداني', 'تخصيص حسب المكتب'],
                ],
            ];
        @endphp

        <div class="grid items-stretch gap-6 lg:grid-cols-3">
            @foreach ($plans as $p)
                <div class="card-lux relative flex flex-col rounded-2xl p-8 {{ $p['featured'] ? '!border-gold/35 bg-gold/5 lg:-translate-y-3 lg:scale-[1.04]' : '' }}">
                    @if (!empty($p['tag']))
                        <span class="absolute -top-3.5 start-6 rounded-full border border-gold/25 bg-ink px-3 py-1 text-[11px] text-gold-soft">{{ $p['tag'] }}</span>
                    @endif
                    <h2 class="text-xl font-bold">{{ $p['name'] }}</h2>
                    <div class="mt-5 flex items-baseline gap-2">
                        <span class="font-latin text-5xl font-semibold text-gold-soft">{{ $p['price'] }}$</span>
                        <span class="text-sm text-muted">{{ $p['per'] }}</span>
                    </div>
                    @if (!empty($p['billingNote']))
                        <p class="mt-2 text-xs text-muted/80">{{ $p['billingNote'] }}</p>
                    @else
                        <p class="mt-2 text-xs text-muted/80">أو ٦٥$ شهريًا عند الدفع السنوي</p>
                    @endif

                    <div class="mt-6 border-t border-ivory/10 pt-5">
                        <p class="mb-3 text-[11px] tracking-widest text-muted">الحدود</p>
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($p['limits'] as $l)
                                <div>
                                    <p class="text-[10px] text-muted/70">{{ $l[0] }}</p>
                                    <p class="text-sm font-semibold">{{ $l[1] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <ul class="mt-6 flex flex-1 flex-col gap-3 border-t border-ivory/10 pt-5 text-sm text-ivory/85">
                        @foreach ($p['features'] as $f)
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-gold/35 text-[11px] text-gold-soft">✓</span>
                                <span>{{ $f }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-8">
                        <a href="{{ $p['featured'] ? route('marketing.register') : ($p['name'] === 'الشركات' ? route('marketing.contact') : route('marketing.register')) }}" class="{{ $p['featured'] ? 'btn-gold' : 'btn-ghost' }} block rounded-full py-3.5 text-center text-sm font-semibold">
                            {{ $p['cta'] }}
                        </a>
                        <p class="mt-3 text-center text-xs text-muted/80">14 يومًا مجانًا — بدون بطاقة ائتمان</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-12 rounded-2xl border border-gold/20 bg-gold/5 p-8 text-center">
            <p class="text-sm text-muted">جميع الباقات تشمل تحديثات مجانية، نسخًا احتياطية مشفرة، ودعمًا فنيًا.</p>
            <a href="{{ route('marketing.contact') }}" class="mt-5 inline-block text-sm font-semibold text-gold-soft underline decoration-gold/40 underline-offset-4 transition-colors hover:text-gold">سؤال عن الأسعار؟ تواصل معنا</a>
        </div>
    </section>
@endsection
