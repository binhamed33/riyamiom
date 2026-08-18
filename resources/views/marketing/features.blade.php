@extends('layouts.marketing')

@section('title', 'المميزات')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">المميزات</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">تسع وحدات، <span class="gold-text">مترابطة</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                كل جزء من عمل مكتبك متصل بالآخر — القضية بموكلها، بمستنداتها، بجلساتها، وبالمسؤول عنها.
            </p>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $modules = [
                    ['t' => 'لوحة التحكم', 'd' => 'نظرة شاملة: القضايا النشطة، الجلسات القادمة، المهام المتأخرة، والمستندات الحديثة في مكان واحد.'],
                    ['t' => 'القضايا', 'd' => 'سجل كامل لكل قضية: بيانات، مراحل، أطراف، مستندات، جلسات، وسجل أنشطة مرتبة.'],
                    ['t' => 'العملاء', 'd' => 'ملف شامل لكل موكل مرتبط بقضاياه ومستنداته وسجل تواصله.'],
                    ['t' => 'الجلسات', 'd' => 'تسجيل موعد كل جلسة مع تنبيه بالقضايا المتأخرة وعرض الجلسات القادمة.'],
                    ['t' => 'المستندات', 'd' => 'مستندات موثقة ومرتبة مرتبطة بالقضايا، مع معاينة وتحميل بضغطة واحدة.'],
                    ['t' => 'المهام', 'd' => 'مهام بمواعيد وأولوية وتعيين مسؤول — لا يضيع شيء من متابعة القضايا.'],
                    ['t' => 'الفريق', 'd' => 'أدوار وصلاحيات لكل عضو في المكتب (محامي، موظف، إدارة) للوصول المناسب.'],
                    ['t' => 'سجل التدقيق', 'd' => 'سجل غير قابل للتعديل لكل عملية — من فعل ماذا ومتى، لشفافية كاملة.'],
                    ['t' => 'الفوترة والتقارير', 'd' => 'حدود الباقة واضحة، تقارير إحصائية، وتصدير بيانات القضايا والجلسات بسهولة.'],
                ];
            @endphp
            @foreach ($modules as $m)
                <div class="card-lux rounded-2xl p-7">
                    <span class="block h-8 w-8 rounded-lg border border-gold/25 bg-gold/10"></span>
                    <h2 class="mt-5 text-lg font-bold text-ivory">{{ $m['t'] }}</h2>
                    <p class="mt-2.5 text-sm leading-8 text-muted">{{ $m['d'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 rounded-2xl border border-gold/20 bg-gold/5 p-8 text-center">
            <h2 class="text-xl font-bold">شاهد المنظومة بالتفصيل</h2>
            <p class="mt-2 text-sm text-muted">تعرّف على كل وحدة بواجهاتها الحقيقية عبر الموقع التعريفي.</p>
            <div class="mt-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ config('marketing.portfolio_url') }}" class="btn-gold rounded-full px-8 py-3.5 text-sm font-semibold">استكشف المنظومة</a>
                <a href="{{ route('marketing.pricing') }}" class="btn-ghost rounded-full px-8 py-3.5 text-sm">اطّلع على الأسعار</a>
            </div>
        </div>
    </section>
@endsection
