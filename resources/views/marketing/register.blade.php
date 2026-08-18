@extends('layouts.marketing')

@section('title', 'التسجيل')

@section('content')
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(120% 90% at 78% 8%, rgba(227,189,98,.06), transparent 45%)"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16 text-center relative">
            <p class="eyebrow text-[11px]">التسجيل</p>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold leading-tight">ابدأ تجربتك المجانية — <span class="gold-text">14 يومًا</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-9 text-muted">
                بدون بطاقة ائتمان، وبدون أي التزام. عبّئ بيانات مكتبك وسنتواصل معك
                لتفعيل الحساب خلال ٢٤–٤٨ ساعة.
            </p>
        </div>
    </section>

    <section id="register-form" class="max-w-3xl mx-auto px-4 sm:px-6 pb-20">
        @if (session('success'))
            <div class="mb-8 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 p-6 text-center">
                <p class="text-lg font-bold text-emerald-300">✓ {{ session('success') }}</p>
                <p class="mt-2 text-sm text-muted">يمكنك إرسال طلب آخر في أي وقت.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('marketing.register.store') }}" class="card-lux rounded-3xl p-8 sm:p-10">
            @csrf

            {{-- حقل فخ للروبوتات — يُترك فارغًا --}}
            <div class="hidden" aria-hidden="true">
                <label for="website">لا تملأ هذا الحقل</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <h2 class="text-xl font-bold">بيانات المكتب</h2>
            <p class="mt-1.5 text-sm text-muted">كل الحقول المطلوبة ضرورية لتجهيز حسابك بالشكل الصحيح.</p>

            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="office_name" class="mb-2 block text-sm font-semibold">اسم المكتب *</label>
                    <input type="text" id="office_name" name="office_name" value="{{ old('office_name') }}" required placeholder="مثال: مكتب الريامي للمحاماة"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-sm text-ivory placeholder:text-muted/50 focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">
                    @error('office_name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="contact_name" class="mb-2 block text-sm font-semibold">الشخص المسؤول *</label>
                    <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required placeholder="الاسم الكامل"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-sm text-ivory placeholder:text-muted/50 focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">
                    @error('contact_name') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="mb-2 block text-sm font-semibold">رقم التواصل (واتساب) *</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required dir="ltr" placeholder="+968 9XXX XXXX"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-left text-sm text-ivory placeholder:text-muted/50 focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">
                    @error('phone') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold">البريد الإلكتروني للمكتب *</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required dir="ltr" placeholder="office@example.com"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-left text-sm text-ivory placeholder:text-muted/50 focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">
                    @error('email') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="lawyers_count" class="mb-2 block text-sm font-semibold">عدد المحامين</label>
                    <select id="lawyers_count" name="lawyers_count"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-sm text-ivory focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">
                        <option value="">اختر عدد المحامين</option>
                        <option value="1" @selected(old('lawyers_count') == 1)>محامٍ واحد</option>
                        <option value="2" @selected(old('lawyers_count') == 2)>٢ – ١٠ محامين</option>
                        <option value="3" @selected(old('lawyers_count') == 3)>١١ – ٥٠ محاميًا</option>
                        <option value="4" @selected(old('lawyers_count') == 4)>أكثر من ٥٠</option>
                    </select>
                    @error('lawyers_count') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="city" class="mb-2 block text-sm font-semibold">المدينة</label>
                    <input type="text" id="city" name="city" value="{{ old('city') }}" placeholder="مثال: مسقط"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-sm text-ivory placeholder:text-muted/50 focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">
                    @error('city') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="notes" class="mb-2 block text-sm font-semibold">ملاحظات (اختياري)</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="هل تستخدم نظامًا حاليًا؟ هل تحتاج مساعدة في ترحيل البيانات؟"
                        class="w-full rounded-xl border border-ivory/15 bg-ink-2/70 px-4 py-3 text-sm text-ivory placeholder:text-muted/50 focus:border-gold/50 focus:outline-none focus:ring-2 focus:ring-gold/20">{{ old('notes') }}</textarea>
                    @error('notes') <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" class="btn-gold mt-9 w-full rounded-full py-4 text-base font-semibold">
                إرسال طلب التسجيل
            </button>
            <p class="mt-4 flex items-center justify-center gap-2 text-xs text-muted/80">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-gold"></span>
                14 يومًا مجانًا — بدون بطاقة ائتمان — إلغاء في أي وقت
            </p>
        </form>

        <div class="mt-12">
            <h2 class="text-center text-lg font-bold">ماذا يحدث بعد الإرسال؟</h2>
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                <div class="card-lux rounded-2xl p-7 text-center">
                    <span class="font-latin mx-auto flex h-10 w-10 items-center justify-center rounded-full border border-gold/30 bg-gold/10 text-base font-bold text-gold-soft">01</span>
                    <h3 class="mt-4 text-base font-bold">نراجع طلبك</h3>
                    <p class="mt-2 text-sm leading-8 text-muted">فريقنا يطّلع على بيانات مكتبك ويتواصل معك للتأكيد.</p>
                </div>
                <div class="card-lux rounded-2xl p-7 text-center">
                    <span class="font-latin mx-auto flex h-10 w-10 items-center justify-center rounded-full border border-gold/30 bg-gold/10 text-base font-bold text-gold-soft">02</span>
                    <h3 class="mt-4 text-base font-bold">نفعّل الحساب</h3>
                    <p class="mt-2 text-sm leading-8 text-muted">ننشئ حساب مكتبك ونرسل بيانات الدخول بالبريد.</p>
                </div>
                <div class="card-lux rounded-2xl p-7 text-center">
                    <span class="font-latin mx-auto flex h-10 w-10 items-center justify-center rounded-full border border-gold/30 bg-gold/10 text-base font-bold text-gold-soft">03</span>
                    <h3 class="mt-4 text-base font-bold">تبدأ العمل</h3>
                    <p class="mt-2 text-sm leading-8 text-muted">سجّل قضاياك وأضف فريقك — والدعم معك خطوة بخطوة.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
