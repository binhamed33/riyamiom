@extends('layouts.marketing')

@section('title', 'المدونة')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">المدونة</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">مقالات <span class="gold-text">قانونية وإدارية</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                نصائح عملية لإدارة مكاتب المحاماة، وأخبار المنظومة.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20">
        {{-- قم بتعبئة المقالات هنا: عدّل العناوين والنصوص أو أضف مقالات جديدة بنفس النمط --}}
        @php
            $posts = [
                ['cat' => 'إدارة المكاتب', 't' => 'كيف تنظم ملفات قضاياك في مكتب رقمي واحد؟', 'd' => 'خطوات عملية للانتقال من الأوراق المتناثرة إلى منظومة رقمية مرتبة تحفظ كل مستند في مكانه.', 'date' => 'قريبًا'],
                ['cat' => 'تقنيات قانونية', 't' => 'الذكاء الاصطناعي في تلخيص المستندات القضائية', 'd' => 'كيف يساعدك تلخيص المستندات الذكي على توفير ساعات كل أسبوع.', 'date' => 'قريبًا'],
                ['cat' => 'الأمان', 't' => 'لماذا يعتبر سجل التدقيق ضرورة لكل مكتب محاماة؟', 'd' => 'حماية المنشأة وبيانات الموكلين تبدأ بشفافية تامة في كل عملية.', 'date' => 'قريبًا'],
            ];
        @endphp
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($posts as $p)
                <article class="card-lux flex flex-col rounded-2xl p-7">
                    <span class="inline-flex w-fit rounded-full border border-gold/25 bg-gold/10 px-3 py-1 text-[11px] text-gold-soft">{{ $p['cat'] }}</span>
                    <h2 class="mt-5 text-lg font-bold leading-relaxed">{{ $p['t'] }}</h2>
                    <p class="mt-3 flex-1 text-sm leading-8 text-muted">{{ $p['d'] }}</p>
                    <p class="mt-6 border-t border-ivory/10 pt-4 text-xs text-muted/70">{{ $p['date'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
