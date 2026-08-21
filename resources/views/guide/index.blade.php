@extends('layouts.app')

@section('title', 'دليل استخدام مُداوَلة')

@push('styles')
<style nonce="{{ $cspNonce ?? '' }}">
    html { scroll-behavior: smooth; }
    .guide-toc-link { transition: all 0.15s ease; border-inline-start: 2px solid transparent; }
    .guide-toc-link.is-active {
        color: #A98218;
        font-weight: 700;
        border-inline-start-color: #D4AF37;
        background: rgba(212, 175, 55, 0.08);
    }
    @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
</style>
@endpush

@section('content')
<div class="flex gap-8 items-start" dir="rtl">

    {{-- ===== المحتوى ===== --}}
    <div class="flex-1 min-w-0 max-w-4xl mx-auto space-y-8">

        {{-- Header --}}
        <div class="text-center py-8">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-gold to-gold/60 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-gold/30">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gold-dark font-heading">📖 دليل استخدام مُداوَلة</h1>
            <p class="text-gray-500 mt-3 text-lg">مرجع كامل لكل أقسام النظام — منظّم حسب ما تراه فعلاً في القائمة الجانبية</p>
        </div>

        {{-- Quick Nav (mobile/top) --}}
        <div class="xl:hidden bg-white/60 backdrop-blur-sm rounded-2xl border border-gray-100 p-6">
            <p class="text-gold-dark font-bold mb-4 text-sm uppercase tracking-wider">محتويات الدليل</p>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2" data-guide-nav>
                @foreach([
                    ['start', '🚀', 'البدء السريع'],
                    ['dashboard', '🏠', 'لوحة التحكم'],
                    ['attention', '⚡', 'مركز الانتباه'],
                    ['clients', '👥', 'العملاء'],
                    ['cases', '📁', 'القضايا'],
                    ['sessions', '📅', 'الجلسات'],
                    ['tasks', '✅', 'المهام'],
                    ['documents', '📄', 'المستندات'],
                    ['search', '🔍', 'البحث والتصفية'],
                    ['chat', '💬', 'المحادثات'],
                    ['users', '🛡️', 'المستخدمون والصلاحيات'],
                    ['hr', '👔', 'الموارد البشرية'],
                    ['finance', '💰', 'المالية'],
                    ['reports', '📊', 'التقارير'],
                    ['notifications', '🔔', 'الإشعارات'],
                    ['assistant', '🤖', 'المساعد الذكي'],
                    ['automation', '⚙️', 'الأتمتة والقوالب'],
                    ['settings', '🗄️', 'الإعدادات والنسخ'],
                    ['profile', '👤', 'الملف الشخصي'],
                    ['tips', '💡', 'نصائح سريعة'],
                ] as [$id, $icon, $label])
                    <a href="#{{ $id }}" data-guide-link="{{ $id }}"
                       class="guide-toc-link flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gold/12 text-gray-700 hover:text-gold-dark text-xs">
                        <span>{{ $icon }}</span> {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 1) البدء السريع --}}
        <section id="start" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🚀</span><h2 class="text-2xl font-bold text-gold-dark">البدء السريع</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تسجيل الدخول:</strong> ادخل ببريدك وكلمة المرور التي سلّمتها لك إدارة المكتب. خيار «تذكرني» يحفظ بريدك على جهازك لتسجيل أسرع.</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">القائمة الجانبية:</strong> كل أقسام النظام فيها، مقسومة إلى «الرئيسية» (لوحة التحكم، مركز الانتباه، القضايا، الجلسات، المهام، المستندات، العملاء، المحادثات) و«الشؤون الإدارية» و«الإدارة». تظهر لك الأقسام حسب دورك وصلاحياتك فقط.</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">اللغة:</strong> زر تبديل اللغة (عربي / English) في أسفل القائمة الجانبية.</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الوضع الليلي:</strong> النظام يدعم الوضع الليلي والنهاري ويحفظ اختيارك تلقائياً.</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 2) لوحة التحكم --}}
        <section id="dashboard" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🏠</span><h2 class="text-2xl font-bold text-gold-dark">لوحة التحكم</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>لوحة التحكم هي <strong class="text-gold-dark">الصفحة الرئيسية</strong> التي تظهر لك أول ما تدخل، وتعطيك صورة سريعة عن المكتب:</p>
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">بطاقات الإحصائيات:</strong> عدد القضايا والعملاء والجلسات والمهام بشكل فوري</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الجلسات القادمة:</strong> جدول جلسات اليوم والأيام القادمة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">آخر المهام:</strong> أحدث المهام المضافة مع حالتها</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">آخر التحديثات:</strong> النشاطات الحديثة على النظام</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 3) مركز الانتباه --}}
        <section id="attention" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">⚡</span><h2 class="text-2xl font-bold text-gold-dark">مركز الانتباه</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>مركز الانتباه يجمع لك <strong class="text-gold-dark">ما يحتاج تدخلك الآن</strong> مرتباً حسب الخطورة (حرج / تنبيه / معلومة)، ولكل بند زر إجراء مباشر:</p>
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-red-500 mt-1">●</span><span><strong class="text-gray-700">جلسة اليوم أو خلال 48 ساعة بلا مهمة تحضير</strong> — مع زر «تحضير الجلسة»</span></li>
                        <li class="flex items-start gap-3"><span class="text-amber-500 mt-1">●</span><span><strong class="text-gray-700">مهام متأخرة عن موعدها</strong> — مع زر «إنهاء المهمة»</span></li>
                        <li class="flex items-start gap-3"><span class="text-amber-500 mt-1">●</span><span><strong class="text-gray-700">قضايا متعثرة</strong> لم تُحدَّث منذ فترة طويلة</span></li>
                        <li class="flex items-start gap-3"><span class="text-amber-500 mt-1">●</span><span><strong class="text-gray-700">فواتير غير مدفوعة</strong> أو متجاوزة الاستحقاق</span></li>
                        <li class="flex items-start gap-3"><span class="text-blue-500 mt-1">●</span><span><strong class="text-gray-700">موكل جديد بدون قضية</strong> — تذكير بإنشاء قضيته</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 4) العملاء --}}
        <section id="clients" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">👥</span><h2 class="text-2xl font-bold text-gold-dark">العملاء</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>قسم العملاء يتيح لك <strong class="text-gold-dark">تسجيل بيانات العملاء</strong> (أفراد وشركات) ومتابعة كل قضاياهم.</p>
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">إضافة عميل:</strong> الاسم، النوع (فرد / شركة)، الهاتف، البريد، العنوان، الرقم المدني، واسم الشركة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">صفحة العميل:</strong> الضغط على اسم العميل يعرض كل قضاياه وجلساته ومستنداته في مكان واحد</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">التصفية:</strong> زر «تصفية» أعلى القائمة يفتح بحثاً بالاسم أو الهاتف أو البريد أو اسم الشركة، وتصفية بنوع العميل وتاريخ التسجيل</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">بوابة الموكل:</strong> يمكن ربط العميل بحساب دخول خاص يتابع منه قضاياه بنفسه</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 5) القضايا --}}
        <section id="cases" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">📁</span><h2 class="text-2xl font-bold text-gold-dark">القضايا</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <h3 class="text-lg font-bold text-gray-700">📌 إضافة قضية جديدة:</h3>
                    <ol class="space-y-2 pr-6 list-decimal text-gray-700 marker:text-gold-dark">
                        <li>اضغط <span class="bg-gold/12 text-gold-dark px-3 py-1 rounded-lg text-sm">+ إضافة قضية</span></li>
                        <li>املأ البيانات: رقم القضية، المحكمة، الموضوع، الخصم، الحالة، النوع، الأولوية</li>
                        <li>اختر <strong>العميل</strong> المرتبط بالقضية (إجباري) و<strong>المحامي المسؤول</strong></li>
                        <li>يمكنك اختيار <strong>قالب قضية</strong> جاهز — فتُنشأ مهام القالب تلقائياً بمواعيدها (انظر قسم الأتمتة والقوالب)</li>
                        <li>اضغط حفظ ✨</li>
                    </ol>
                    <p class="mt-2">داخل ملف القضية تجد <strong class="text-gold-dark">المستندات المرفقة والجلسات والمهام</strong> المرتبطة بها، وسجل تحديثاتها.</p>
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الفرز:</strong> اضغط على عنوان أي عمود في الجدول (الرقم، المحكمة، الموكل، الحالة…) للفرز تصاعدياً أو تنازلياً</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">التصفية:</strong> زر «تصفية» يفتح بحثاً وتصفية بالحالة والأولوية والمحامي والمحكمة والنوع وفترة الإنشاء — مع عدّاد يبين كم فلتراً مفعّلاً وزر «مسح الفلاتر»</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">القضايا الشهرية:</strong> صفحة خاصة بجدول الشهر مع طباعة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تقرير المتأخرات:</strong> زر «تقرير المتأخرات» يكشف القضايا المتجاوزة تلقائياً</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 6) الجلسات --}}
        <section id="sessions" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">📅</span><h2 class="text-2xl font-bold text-gold-dark">الجلسات</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>تابع <strong class="text-gold-dark">جلسات المحكمة</strong> لكل قضية: إضافة وتعديل وتغيير حالة (قادمة / منعقدة / مؤجلة / ملغاة) مع الملاحظات ومحضر الجلسة.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            <p class="text-gold-dark font-bold mb-2">📌 إضافة جلسة:</p>
                            <ul class="text-gray-700 text-sm space-y-1 pr-4">
                                <li>• اختر القضية من القائمة</li>
                                <li>• حدد التاريخ والوقت والمكان</li>
                                <li>• حدد الحالة وأضف الملاحظات</li>
                            </ul>
                        </div>
                        <div class="bg-white rounded-xl p-4 border border-gray-100">
                            <p class="text-gold-dark font-bold mb-2">🔎 التصفية:</p>
                            <p class="text-gray-700 text-sm">زر «تصفية» يتيح التصفية بالحالة والقضية والمحامي والمحكمة، وفترات سريعة (اليوم / هذا الأسبوع / هذا الشهر) أو تاريخ محدد من–إلى.</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-4 border border-gray-100">
                        <p class="text-gray-600 text-sm">💡 <strong class="text-gold-dark">تلقائياً:</strong> عند تفعيل الأتمتة، أي جلسة خلال 3 أيام بلا مهمة تحضير يُنشأ لها «تحضير جلسة» تلقائياً، وبعد انعقاد الجلسة تُنشأ مهمة «متابعة ما بعد الجلسة».</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- 7) المهام --}}
        <section id="tasks" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">✅</span><h2 class="text-2xl font-bold text-gold-dark">المهام</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">إنشاء مهمة:</strong> العنوان، الوصف، الأولوية (منخفضة / متوسطة / عالية / عاجلة)، تاريخ الاستحقاق، والموظف المسؤول، مع إمكانية ربطها بقضية</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الحالات:</strong> معلقة / قيد الإنجاز / منجزة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">التبويبات السريعة:</strong> فوق القائمة: الكل / اليوم / قادمة / متأخرة / منجزة — ضغطة واحدة تصفّي القائمة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">التصفية المتقدمة:</strong> زر «تصفية» للتصفية بالحالة والأولوية والمسؤول والاستحقاق (مستحقة اليوم / خلال أسبوع / قادمة / متأخرة)</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الإشعارات:</strong> إسناد مهمة لموظف يصله إشعار داخل النظام مباشرة</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 8) المستندات --}}
        <section id="documents" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">📄</span><h2 class="text-2xl font-bold text-gold-dark">المستندات</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>مستودعك الرقمي لكل الملفات التي تخص القضايا والعملاء.</p>
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">رفع مستند:</strong> اختر الملف، اكتب وصفاً، واربطه بقضية أو عميل</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تصفح وتحميل:</strong> المستندات مرتبة بتاريخ الإضافة وكل تحميل مسجّل باسم صاحبه</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الأنواع المدعومة:</strong> PDF، صور (JPG / PNG)، Word، Excel</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 9) البحث والتصفية --}}
        <section id="search" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🔍</span><h2 class="text-2xl font-bold text-gold-dark">البحث والتصفية</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">البحث الفوري في القضايا:</strong> مربع البحث في صفحة القضايا يقترح النتائج وأنت تكتب — اختر نتيجة بالأسهم وEnter</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">زر «تصفية» الموحّد:</strong> في القضايا والجلسات والمهام والعملاء — يفتح وينغلق بسلاسة، يعرض <strong>عدد الفلاتر المفعّلة</strong> على الزر، ومعه «مسح الفلاتر» بضغطة واحدة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تُنفَّذ التصفية على الخادم:</strong> النتائج مقسّمة صفحات وتحافظ على فلاترك أثناء التنقل بينها</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 10) المحادثات --}}
        <section id="chat" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">💬</span><h2 class="text-2xl font-bold text-gold-dark">المحادثات</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>تواصل داخلي فوري وآمن بين أعضاء المكتب:</p>
                    <ul class="space-y-2 pr-6 text-sm">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>إرسال رسائل نصية وإرفاق ملفات وصور 📎</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>شارة غير مقروء على المحادثات وصوت تنبيه عند وصول رسالة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>مؤشر اتصال يوضح من متصل الآن</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 11) المستخدمون والصلاحيات --}}
        <section id="users" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🛡️</span><h2 class="text-2xl font-bold text-gold-dark">المستخدمون والصلاحيات</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>قسم «المستخدمون» (للإدارة) هو مكان إدارة فريق المكتب:</p>
                    <ul class="space-y-3 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الأدوار:</strong> مدير، محامٍ، موظف، وعميل (بوابة الموكل) — كلٌّ يرى ما يخصه فقط؛ فالمحامي مثلاً يرى قضاياه وجلساته ومهامه هو</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">صلاحيات دقيقة:</strong> إلى جانب الدور، تُمنح صلاحيات مفصّلة فرداً فرداً (إدارة النسخ الاحتياطي، المالية، التقارير…)</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تفعيل / تعطيل حساب:</strong> يمكن إيقاف حساب موظف دون حذف بياناته</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 12) الموارد البشرية --}}
        <section id="hr" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">👔</span><h2 class="text-2xl font-bold text-gold-dark">الموارد البشرية</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">سجل الموظفين:</strong> بيانات جميع موظفي المكتب</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الإجازات:</strong> تقديم الطلبات واعتمادها (سنوية / مرضية / طارئة) مع إشعار صاحب الطلب بالنتيجة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الإحصائيات:</strong> رسوم توضح توزيع الموظفين والإجازات</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 13) المالية --}}
        <section id="finance" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">💰</span><h2 class="text-2xl font-bold text-gold-dark">المالية</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">المعاملات:</strong> تسجيل المقبوضات والمدفوعات والفواتير بتواريخها</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">إرفاق المستندات:</strong> فواتير وإيصالات لكل معاملة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">طباعة:</strong> طباعة تفاصيل المعاملة بنموذج مرتب</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">في مركز الانتباه:</strong> الفواتير غير المدفوعة تظهر تلقائياً حتى لا تُنسى</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 14) التقارير --}}
        <section id="reports" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">📊</span><h2 class="text-2xl font-bold text-gold-dark">التقارير والتصدير</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">التقارير المتاحة:</strong> القضايا، العملاء، الجلسات، المهام، المستندات، المالية، الموارد البشرية — بصيغة Excel</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">التصدير الشامل:</strong> كل البيانات مرة واحدة مع صفحة ملخص</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تنسيق عربي:</strong> رؤوس ملوّنة ومحاذاة يمين جاهزة للطباعة</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 15) الإشعارات --}}
        <section id="notifications" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🔔</span><h2 class="text-2xl font-bold text-gold-dark">الإشعارات</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">جرس الإشعارات:</strong> أعلى الصفحة، يعرض إشعاراتك (مهمة أُسندت لك، جلسة اقتربت، رد على طلب إجازة…) مع عدّاد غير المقروء</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">صوت تنبيه:</strong> يصدر عند وصول إشعار أو رسالة جديدة</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">رسالة اليوم:</strong> تعلن بها الإدارة أو المطوّر عن مستجدات النظام، وتظهر مرة واحدة لكل مستخدم</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 16) المساعد الذكي --}}
        <section id="assistant" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🤖</span><h2 class="text-2xl font-bold text-gold-dark">المساعد الذكي</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <p>مساعد ذكاء اصطناعي داخل النظام، مقيّد بالشأن القانوني العُماني وشؤون مكتبك:</p>
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>اسأله عن إجراء قانوني أو استفسار متعلق بعمل المكتب</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>يحفظ سجل محادثتك ويمكنك مسحه في أي وقت</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>إجاباته استرشادية — القرار القانوني النهائي دائماً للمحامي</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 17) الأتمتة والقوالب --}}
        <section id="automation" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">⚙️</span><h2 class="text-2xl font-bold text-gold-dark">الأتمتة وقوالب القضايا</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <h3 class="text-lg font-bold text-gray-700">🔁 الأتمتة الذكية (عند تفعيلها):</h3>
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تحضير الجلسات:</strong> أي جلسة قادمة خلال 3 أيام بلا مهمة تحضير → تُنشأ مهمة «تحضير جلسة» تلقائياً للمحامي المسؤول</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">متابعة ما بعد الجلسة:</strong> في اليوم التالي لانعقاد الجلسة تُنشأ مهمة متابعة (تسجيل النتيجة والخطوة التالية)</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">القضايا الراكدة:</strong> قضية نشطة بلا أي تحديث 14 يوماً → إشعار تنبيه للإدارة</span></li>
                    </ul>
                    <h3 class="text-lg font-bold text-gray-700 mt-4">📋 قوالب القضايا (للإدارة):</h3>
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>أنشئ قالباً باسم النوع (مثلاً «قضية عمالية») وحدد مهامه: العنوان، بعد كم يوم، والأولوية</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span>عند إنشاء قضية جديدة اختر القالب — فتُنشأ كل مهامه تلقائياً بمواعيدها</span></li>
                    </ul>
                    <div class="bg-white rounded-xl p-4 border border-gray-100">
                        <p class="text-gray-600 text-sm">💡 تفعيل الأتمتة وإيقافها يتم من لوحة المطوّر بطلب من إدارة المكتب.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- 18) الإعدادات والنسخ الاحتياطي --}}
        <section id="settings" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">🗄️</span><h2 class="text-2xl font-bold text-gold-dark">الإعدادات والنسخ الاحتياطي</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">الإعدادات (للإدارة):</strong> بيانات المكتب والخيارات العامة للنظام</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">النسخ الاحتياطي:</strong> إلى جانب النسخ اليومي التلقائي للمنصة، يمكن للإدارة إنشاء نسخة يدوية من صفحة النسخ الاحتياطي</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">سجل التدقيق:</strong> من فعل ماذا ومتى — كل عملية إنشاء وتعديل وحذف مسجّلة باسم صاحبها</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 19) الملف الشخصي --}}
        <section id="profile" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">👤</span><h2 class="text-2xl font-bold text-gold-dark">الملف الشخصي</h2></div>
                <div class="space-y-4 text-gray-700 leading-relaxed">
                    <ul class="space-y-2 pr-6">
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تعديل بياناتك:</strong> الاسم، البريد، رقم الجوال</span></li>
                        <li class="flex items-start gap-3"><span class="text-gold-dark mt-1">•</span><span><strong class="text-gray-700">تغيير كلمة المرور:</strong> من نفس الشاشة بإدخال الكلمة الحالية ثم الجديدة</span></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 20) نصائح --}}
        <section id="tips" class="scroll-mt-24">
            <div class="guide-card bg-gradient-to-br from-white to-gray-100 rounded-2xl border border-gray-200 p-8">
                <div class="flex items-center gap-4 mb-6"><span class="text-3xl">💡</span><h2 class="text-2xl font-bold text-gold-dark">نصائح سريعة</h2></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl p-5 border border-gray-100">
                        <p class="text-gold-dark font-bold mb-2">⚡ ابدأ يومك من مركز الانتباه</p>
                        <p class="text-gray-700 text-sm">دقيقة واحدة فيه صباحاً تريك كل ما يحتاج تدخلك اليوم مرتباً حسب الخطورة.</p>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-100">
                        <p class="text-gold-dark font-bold mb-2">🔍 استخدم التبويبات السريعة</p>
                        <p class="text-gray-700 text-sm">في المهام: «اليوم» و«متأخرة» أسرع طريق لمعرفة ما عليك الآن.</p>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-100">
                        <p class="text-gold-dark font-bold mb-2">🌙 الوضع الليلي / النهاري</p>
                        <p class="text-gray-700 text-sm">النظام يدعم الوضعين ويحفظ اختيارك تلقائياً على جهازك.</p>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-100">
                        <p class="text-gold-dark font-bold mb-2">📱 متوافق مع الجوال</p>
                        <p class="text-gray-700 text-sm">يعمل على الجوال والتابلت، والقائمة الجانبية تنطوي لتوسيع الشاشة.</p>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-100">
                        <p class="text-gold-dark font-bold mb-2">🔐 الأمان</p>
                        <p class="text-gray-700 text-sm">لا تشارك كلمة مرورك مع أحد. كل عملية في النظام مسجّلة في سجل التدقيق.</p>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-gray-100">
                        <p class="text-gold-dark font-bold mb-2">🔄 تحديث تلقائي</p>
                        <p class="text-gray-700 text-sm">الشارات والإشعارات تتحدث تلقائياً دون إعادة تحميل الصفحة.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <div class="text-center py-10 text-gray-400 text-sm border-t border-gray-100">
            <p>تم تطويره بواسطة <span class="text-gold-light">عبدالرحمن الريامي</span> — مُداوَلة</p>
        </div>
    </div>

    {{-- ===== فهرس جانبي ثابت (شاشات كبيرة) ===== --}}
    <aside class="hidden xl:block w-52 shrink-0 sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto" data-guide-nav>
        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-2">محتويات الدليل</p>
        <nav class="space-y-0.5 text-sm">
            @foreach([
                ['start', 'البدء السريع'],
                ['dashboard', 'لوحة التحكم'],
                ['attention', 'مركز الانتباه'],
                ['clients', 'العملاء'],
                ['cases', 'القضايا'],
                ['sessions', 'الجلسات'],
                ['tasks', 'المهام'],
                ['documents', 'المستندات'],
                ['search', 'البحث والتصفية'],
                ['chat', 'المحادثات'],
                ['users', 'المستخدمون والصلاحيات'],
                ['hr', 'الموارد البشرية'],
                ['finance', 'المالية'],
                ['reports', 'التقارير'],
                ['notifications', 'الإشعارات'],
                ['assistant', 'المساعد الذكي'],
                ['automation', 'الأتمتة والقوالب'],
                ['settings', 'الإعدادات والنسخ'],
                ['profile', 'الملف الشخصي'],
                ['tips', 'نصائح سريعة'],
            ] as [$id, $label])
                <a href="#{{ $id }}" data-guide-link="{{ $id }}"
                   class="guide-toc-link block px-3 py-1.5 rounded-lg text-gray-500 hover:text-gold-dark">{{ $label }}</a>
            @endforeach
        </nav>
    </aside>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
// إبراز القسم النشط في فهرس الدليل أثناء التمرير (Scrollspy)
document.addEventListener('DOMContentLoaded', function () {
    var sections = Array.prototype.slice.call(document.querySelectorAll('section[id]'));
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-guide-link]'));
    if (!sections.length || !links.length || !('IntersectionObserver' in window)) return;

    var setActive = function (id) {
        links.forEach(function (l) {
            l.classList.toggle('is-active', l.getAttribute('data-guide-link') === id);
        });
    };

    var visible = {};
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { visible[e.target.id] = e.isIntersecting; });
        for (var i = 0; i < sections.length; i++) {
            if (visible[sections[i].id]) { setActive(sections[i].id); break; }
        }
    }, { rootMargin: '-20% 0px -60% 0px' });

    sections.forEach(function (s) { io.observe(s); });
});
</script>
@endsection
