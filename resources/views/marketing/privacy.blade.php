@extends('layouts.marketing')

@section('title', 'سياسة الخصوصية')

@section('content')
    <section class="max-w-3xl mx-auto px-4 sm:px-6 pt-16 sm:pt-20 pb-20">
        <p class="eyebrow text-[11px]">الخصوصية</p>
        <h1 class="mt-5 text-3xl sm:text-4xl font-extrabold leading-tight">سياسة الخصوصية</h1>
        <p class="mt-2 text-xs text-muted/70">آخر تحديث: {{ date('Y-m-d') }}</p>

        <div class="mt-10 flex flex-col gap-10 text-sm leading-9 text-muted">
            <section>
                <h2 class="text-lg font-bold text-ivory">١. مقدمة</h2>
                <p class="mt-3">
                    {{ config('marketing.office_name') }} تلتزم بحماية خصوصية عملائها وبياناتهم.
                    توضح هذه السياسة كيفية جمع البيانات واستخدامها وحمايتها عند استخدامك لمنظومة مُداوَلة.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٢. البيانات التي نجمعها</h2>
                <p class="mt-3">
                    بيانات الحساب (الاسم، البريد الإلكتروني، رقم الهاتف)، بيانات القضايا والعملاء
                    والمستندات التي تسجلها داخل المنظومة، وبيانات الاستخدام التقنية الأساسية
                    لضمان أمان الخدمة وأدائها.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٣. استخدام البيانات</h2>
                <p class="mt-3">
                    تستخدم البيانات حصريًا لتشغيل الخدمة وتقديم الدعم الفني وتحسين التجربة.
                    لا نبيع بياناتك لأي طرف ثالث، ولا نستخدمها لأغراض إعلانية.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٤. حماية البيانات</h2>
                <p class="mt-3">
                    البيانات مشفّرة أثناء النقل والتخزين، مع فصل كامل بين بيانات كل مكتب،
                    وصلاحيات وصول دقيقة، وسجل تدقيق لكل عملية، ونسخ احتياطية يومية مشفّرة.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٥. حقوقك</h2>
                <p class="mt-3">
                    يحق لك طلب نسخة من بياناتك أو تصحيحها أو حذفها في أي وقت عبر التواصل معنا.
                </p>
            </section>
            <section>
                <h2 class="text-lg font-bold text-ivory">٦. التواصل</h2>
                <p class="mt-3">
                    لأي استفسار حول هذه السياسة:
                    <span class="font-latin" dir="ltr">{{ config('marketing.email') }}</span>
                    — {{ config('marketing.phone') }}
                </p>
            </section>
        </div>
    </section>
@endsection
