@extends('layouts.app')

@section('title', 'دليل استخدام النظام')

@section('content')
<div class="max-w-4xl mx-auto space-y-8" dir="rtl">

    {{-- Header --}}
    <div class="text-center py-8">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-gold to-gold/60 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-gold/20">
            <svg class="w-8 h-8 text-navy-darkest" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <h1 class="text-4xl font-bold text-gold font-heading">📖 دليل استخدام النظام</h1>
        <p class="text-ivory/50 mt-3 text-lg">كل ما تحتاج لمعرفته للبدء في استخدام مكتب المحاماة الإلكتروني</p>
    </div>

    {{-- Quick Nav --}}
    <div class="bg-navy-light/60 backdrop-blur-sm rounded-2xl border border-ivory/5 p-6">
        <p class="text-gold font-bold mb-4 text-sm uppercase tracking-wider">محتويات الدليل</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            <a href="#dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">🏠</span>
                لوحة التحكم
            </a>
            <a href="#cases" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">📁</span>
                إدارة القضايا
            </a>
            <a href="#clients" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">👥</span>
                إدارة العملاء
            </a>
            <a href="#sessions" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">📅</span>
                الجلسات القضائية
            </a>
            <a href="#tasks" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">✅</span>
                المهام
            </a>
            <a href="#documents" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">📄</span>
                المستندات
            </a>
            <a href="#chat" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">💬</span>
                المحادثات
            </a>
            <a href="#hr" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">👔</span>
                الموارد البشرية
            </a>
            <a href="#finance" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">💰</span>
                المالية
            </a>
            <a href="#reports" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">📊</span>
                التقارير والتصدير
            </a>
            <a href="#profile" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">⚙️</span>
                الملف الشخصي والإعدادات
            </a>
            <a href="#tips" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 hover:bg-gold/10 transition text-ivory/70 hover:text-gold text-sm">
                <span class="w-8 h-8 rounded-lg bg-gold/15 flex items-center justify-center flex-shrink-0 text-gold text-base">💡</span>
                نصائح سريعة
            </a>
        </div>
    </div>

    {{-- Section: Dashboard --}}
    <section id="dashboard" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">🏠</span>
                <h2 class="text-2xl font-bold text-gold">لوحة التحكم</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>لوحة التحكم هي <strong class="text-gold">الصفحة الرئيسية</strong> اللي بتظهر لك أول ما تدخل. من هنا تقدر تشوف كل شيء重要 بشكل مختصر:</p>
                <ul class="space-y-3 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">بطاقات الإحصائيات:</strong> تشوف عدد القضايا، العملاء، الجلسات، والمهام الفورية</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">الجلسات القادمة:</strong> جدول جلسات اليوم والأيام الجاية</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">آخر المهام:</strong> أحدث المهام المضافة مع حالتها</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">آخر التحديثات:</strong> نشاطات حديثة على النظام</span>
                    </li>
                </ul>
                <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5 mt-4">
                    <p class="text-ivory/60 text-sm">💡 <strong class="text-gold">نصيحة:</strong> لو تحس إن البيانات قديمة، استخدم Ctrl+F5 عشان تحديث الصفحة من الكاش.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Cases --}}
    <section id="cases" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">📁</span>
                <h2 class="text-2xl font-bold text-gold">إدارة القضايا</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>في قسم القضايا تقدر <strong class="text-gold">تضيف، تعدل، تحذف، وتتصفح</strong> كل القضايا المسجلة في النظام.</p>
                <h3 class="text-lg font-bold text-ivory mt-6 mb-3">📌 إضافة قضية جديدة:</h3>
                <ol class="space-y-2 pr-6 list-decimal text-ivory/70 marker:text-gold">
                    <li>اضغط على زر <span class="bg-gold/15 text-gold px-3 py-1 rounded-lg text-sm">+ إضافة قضية</span></li>
                    <li>املأ البيانات: رقم القضية، المحكمة، الموضوع، الخصم، الحالة، القسم، النوع</li>
                    <li>اختر <strong>العميل</strong> المرتبط بالقضية (إجباري)</li>
                    <li>اختر <strong>الموظف المسؤول</strong> (إن وجد)</li>
                    <li>اضغط حفظ ✨</li>
                </ol>
                <p class="mt-4">لكل قضية تقدر <strong class="text-gold">تضيف ملفات مرفقة</strong> (مستندات PDF، صور، إلخ) وتشوف <strong>الجلسات</strong> و <strong>المهام</strong> المرتبطة بها.</p>
                <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5 mt-4">
                    <p class="text-ivory/60 text-sm">🔍 <strong class="text-gold">بحث:</strong> في أعلى جدول القضايا، في مربع بحث يسمح لك تبحث عن أي قضية برقم القضية أو اسم العميل.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Clients --}}
    <section id="clients" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">👥</span>
                <h2 class="text-2xl font-bold text-gold">إدارة العملاء</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>قسم العملاء يسمح لك <strong class="text-gold">تسجل بيانات العملاء</strong> وتتابع كل قضاياهم.</p>
                <ul class="space-y-3 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">إضافة عميل:</strong> الاسم، رقم الهاتف، البريد الإلكتروني، العنوان، ملاحظات</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">عرض العميل:</strong> الضغط على اسم العميل يوديك لصفحة العميل وفيها كل قضاياه وجلساته ومستنداته</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">إضافة قضية أثناء إنشاء العميل:</strong> في شاشة إضافة عميل، تقدر تختار "إضافة قضية" عشان تربط قضية جديدة بالعميل فوراً</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Section: Sessions --}}
    <section id="sessions" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">📅</span>
                <h2 class="text-2xl font-bold text-gold">الجلسات القضائية</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>تابع <strong class="text-gold">جلسات المحكمة</strong> لكل قضية. تقدر تضيف جلسة جديدة، تعدلها، أو تحذفها.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5">
                        <p class="text-gold font-bold mb-2">📌 إضافة جلسة:</p>
                        <ul class="text-ivory/70 text-sm space-y-1 pr-4">
                            <li>• اختر القضية من القائمة المنسدلة</li>
                            <li>• حدد تاريخ الجلسة و الوقت</li>
                            <li>• اختر نوع الجلسة ونوع الطلب</li>
                            <li>• أضف ملاحظات إن وجدت</li>
                        </ul>
                    </div>
                    <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5">
                        <p class="text-gold font-bold mb-2">⭐ جلسات اليوم:</p>
                        <p class="text-ivory/70 text-sm">في لوحة التحكم وداخل قسم الجلسات، في تبويب <span class="text-gold">"جلسات اليوم"</span> يعرض لك كل جلسات اليوم مباشرة.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Tasks --}}
    <section id="tasks" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">✅</span>
                <h2 class="text-2xl font-bold text-gold">المهام</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>نظام المهام يساعدك <strong class="text-gold">تنظم أعمالك اليومية</strong> وتتابع إنجازها مع الفريق.</p>
                <ul class="space-y-3 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">إنشاء مهمة:</strong> حدد العنوان، الوصف، الأولوية (عادية / مهمة / عاجلة)، تاريخ الاستحقاق، والموظف المسؤول</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">حالة المهمة:</strong> تقدر تحدد الحالة (معلق / قيد الإنجاز / منجز / ملغي)</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">ربط المهمة بقضية:</strong> تقدر تربط المهمة بقضية معينة عشان تكون مرتبطة بسياقها</span>
                    </li>
                </ul>
                <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5 mt-4">
                    <p class="text-ivory/60 text-sm">🎯 <strong class="text-gold">الأولويات:</strong> المهام العاجلة تظهر باللون الأحمر، المهمة بالبرتقالي، والعادية بالأزرق.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: Documents --}}
    <section id="documents" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">📄</span>
                <h2 class="text-2xl font-bold text-gold">المستندات</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>قسم المستندات هو <strong class="text-gold">مستودعك الرقمي</strong> لكل الملفات والمستندات اللي تخص القضايا والعملاء.</p>
                <ul class="space-y-3 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">رفع مستند:</strong> اسحب الملف أو اختر من جهازك، اكتب وصف للمستند، واربطه بقضية أو عميل</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">تصفح وتحميل:</strong> كل المستندات مرتبة بتاريخ الإضافة، تقدر تفتحها أو تحملها بضغطة زر</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">أنواع الملفات المدعومة:</strong> PDF، صور (JPG, PNG)، Word، Excel</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Section: Chat --}}
    <section id="chat" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">💬</span>
                <h2 class="text-2xl font-bold text-gold">المحادثات</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>نظام المحادثات الداخلية يسمح لك <strong class="text-gold">تتواصل مع زملائك</strong> في المكتب بشكل فوري وآمن.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5">
                        <p class="text-gold font-bold mb-2">📌 إرسال رسالة:</p>
                        <ul class="text-ivory/70 text-sm space-y-1 pr-4">
                            <li>• اختر محادثة من القائمة اليمين</li>
                            <li>• اكتب رسالتك في مربع النص</li>
                            <li>• (اختياري) أرفق ملف بالضغط على 📎</li>
                            <li>• اضغط Enter أو زر الإرسال</li>
                        </ul>
                    </div>
                    <div class="bg-navy-darker/50 rounded-xl p-4 border border-ivory/5">
                        <p class="text-gold font-bold mb-2">⭐ مميزات:</p>
                        <ul class="text-ivory/70 text-sm space-y-1 pr-4">
                            <li>• 🔔 صوت وصول رسائل جديد (يشتغل تلقائياً)</li>
                            <li>• 📎 إرفاق ملفات وصور</li>
                            <li>• 🔵 شارة غير مقروءة على المحادثات</li>
                            <li>• 🟢 مؤشر اتصال (متصل / غير متصل)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Section: HR --}}
    <section id="hr" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">👔</span>
                <h2 class="text-2xl font-bold text-gold">الموارد البشرية</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>قسم الموارد البشرية يسمح لك <strong class="text-gold">إدارة شؤون الموظفين</strong> في المكتب.</p>
                <ul class="space-y-2 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">سجل الموظفين:</strong> متابعة بيانات جميع الموظفين في المكتب</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">الإجازات:</strong> تقديم واعتماد طلبات الإجازات (إجازة سنوية، مرضية، طارئة)</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">الإحصائيات:</strong> رسوم بيانية توضح توزيع الموظفين والإجازات</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Section: Finance --}}
    <section id="finance" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">💰</span>
                <h2 class="text-2xl font-bold text-gold">المالية</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>نظام المالية يساعدك <strong class="text-gold">تتابع كل المعاملات المالية</strong> في المكتب.</p>
                <ul class="space-y-2 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">المعاملات المالية:</strong> تسجيل كل المدفوعات والمقبوضات مع تاريخها</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">إرفاق المستندات:</strong> إرفاق فواتير وإيصالات لكل معاملة</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">طباعة وتصدير:</strong> طباعة تفاصيل المعاملة أو تصديرها</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Section: Reports --}}
    <section id="reports" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">📊</span>
                <h2 class="text-2xl font-bold text-gold">التقارير والتصدير</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>قسم التقارير يسمح لك <strong class="text-gold">تصدير بيانات النظام</strong> على شكل Excel.</p>
                <ul class="space-y-2 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">التقارير المتاحة:</strong> تقارير القضايا، العملاء، الجلسات، المهام، المستندات، المالية، الموارد البشرية</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">التصدير الشامل:</strong> تصدير كل البيانات مرة واحدة مع صفحة ملخص</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">تصميم التقرير:</strong> رأس ذهبي، خط أبيض عريض، صفوف مخططة، محاذاة عربية</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Section: Profile --}}
    <section id="profile" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">⚙️</span>
                <h2 class="text-2xl font-bold text-gold">الملف الشخصي والإعدادات</h2>
            </div>
            <div class="space-y-4 text-ivory/80 leading-relaxed">
                <p>من هنا تقدر <strong class="text-gold">تعدل بيانات حسابك الشخصي</strong> وتغير كلمة المرور.</p>
                <ul class="space-y-2 pr-6">
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">تعديل الملف الشخصي:</strong> الاسم، البريد الإلكتروني، رقم الجوال</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">تغيير كلمة المرور:</strong> من نفس الشاشة، اكتب كلمة المرور الحالية والجديدة</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-gold mt-1">•</span>
                        <span><strong class="text-ivory">تبديل اللغة:</strong> من الشريط الجانبي، تقدر تحول بين العربية والإنجليزية</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Section: Tips --}}
    <section id="tips" class="scroll-mt-24">
        <div class="bg-gradient-to-br from-navy-light to-navy-lighter rounded-2xl border border-ivory/10 p-8">
            <div class="flex items-center gap-4 mb-6">
                <span class="text-3xl">💡</span>
                <h2 class="text-2xl font-bold text-gold">نصائح سريعة</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
                    <p class="text-gold font-bold mb-2">🔄 التحديثات المباشرة</p>
                    <p class="text-ivory/70 text-sm">النظام يحدث تلقائياً كل 30 ثانية. أي تغيير يسويه أحد الزملاء راح يظهر لك مباشرة بدون ما تعيد تحميل الصفحة.</p>
                </div>
                <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
                    <p class="text-gold font-bold mb-2">🔊 أصوات التنبيهات</p>
                    <p class="text-ivory/70 text-sm">لما توصلك رسالة جديدة في الشات أو إشعار جديد، راح تسمع صوت تنبيه. تأكد إن الصوت شغال على جهازك.</p>
                </div>
                <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
                    <p class="text-gold font-bold mb-2">🔍 البحث السريع</p>
                    <p class="text-ivory/70 text-sm">في كل جدول تقدر تبحث باستعمال مربع البحث. جرب تكتب أي كلمة وراح تظهر النتائج فوراً.</p>
                </div>
                <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
                    <p class="text-gold font-bold mb-2">🌙 الوضع الليلي / النهاري</p>
                    <p class="text-ivory/70 text-sm">النظام يدعم الوضع الليلي (أزرق داكن) والوضع النهاري (بيج فاتح). تقدر تغير من إعدادات العرض.</p>
                </div>
                <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
                    <p class="text-gold font-bold mb-2">📱 متوافق مع الجوال</p>
                    <p class="text-ivory/70 text-sm">النظام يشتغل على الجوال والتابلت أيضاً. القائمة الجانبية تنطوي عشان تعطي مساحة أكبر للشاشة.</p>
                </div>
                <div class="bg-navy-darker/50 rounded-xl p-5 border border-ivory/5">
                    <p class="text-gold font-bold mb-2">🔐 الأمان</p>
                    <p class="text-ivory/70 text-sm">جميع البيانات في النظام مشفرة. لا تشارك كلمة المرور مع أي شخص. في حال نسيت كلمة المرور، تواصل مع المطور.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <div class="text-center py-10 text-ivory/30 text-sm border-t border-ivory/5">
        <p>تم تطويره بواسطة <span class="text-gold/60">مهند بن حامد</span> — 2026</p>
    </div>
</div>
@endsection
