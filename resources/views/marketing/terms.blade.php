@extends('layouts.marketing')

@section('title', 'الشروط والأحكام')

@section('content')
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 sm:pt-20 pb-20">
        <p class="eyebrow text-[11px]">الشروط</p>
        <h1 class="mt-5 text-3xl sm:text-4xl font-extrabold leading-tight">الشروط والأحكام</h1>
        <p class="mt-2 text-xs text-muted/70">آخر تحديث: {{ date('Y-m-d') }}</p>

        <div class="mt-10 flex flex-col gap-10 text-sm leading-9 text-muted">
            <section>
                <h2 class="text-lg font-bold text-ivory">١. قبول الشروط</h2>
                <p class="mt-3">
                    باستخدامك لمنظومة مُداوَلة المقدمة من {{ config('marketing.office_name') }}
                    فإنك توافق على هذه الشروط. إذا لم توافق عليها، يرجى عدم استخدام الخدمة.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٢. الحساب والمسؤولية</h2>
                <p class="mt-3">
                    أنت مسؤول عن الحفاظ على سرية بيانات الدخول الخاصة بك، وعن جميع الأنشطة
                    التي تتم عبر حسابك. أبلغنا فورًا بأي استخدام غير مصرح به.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٣. التجربة المجانية والاشتراك</h2>
                <p class="mt-3">
                    التجربة المجانية ١٤ يومًا بدون بطاقة ائتمان. بعد انتهائها يمكنك اختيار الباقة
                    المناسبة أو إيقاف الخدمة دون أي رسوم. قد تُحدث الأسعار والباقات من وقت لآخر
                    مع إشعار مسبق.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٤. الخدمة والتوافر</h2>
                <p class="mt-3">
                    نسعى لتوفير خدمة مستقرة على مدار الساعة، مع فترات صيانة محدودة معلنة مسبقًا.
                    لا نتحمل مسؤولية انقطاعات الطرف الثالث خارج سيطرتنا.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٥. سرية بيانات العملاء</h2>
                <p class="mt-3">
                    بيانات قضايا وعملاء مكتبك سرية تمامًا وتخضع لسياسة الخصوصية.
                    لا تطلع عليها المنظومة أو موظفوها إلا لغرض الدعم الفني وبإذنك.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٦. الإنهاء</h2>
                <p class="mt-3">
                    يمكنك إيقاف الخدمة في أي وقت. عند الإنهاء يمكنك تصدير بياناتك
                    خلال فترة معقولة قبل إغلاق الحساب نهائيًا.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٧. التواصل القانوني</h2>
                <p class="mt-3">
                    لأي استفسار حول هذه الشروط:
                    <span class="font-latin" dir="ltr">{{ config('marketing.email') }}</span>
                    — {{ config('marketing.phone') }}
                </p>
            </section>
        </div>
    </section>
@endsection
