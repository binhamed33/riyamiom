{{-- نموذجُ الموعد: حقولٌ واحدةٌ للحجز والتعديل، والفُسَحُ تُجلب حيّةً --}}
@php
    $appointment = $appointment ?? null;
    $selectedClient = old('client_id', $appointment?->client_id ?? ($presetClient ?? 0));
    $selectedCase = old('case_id', $appointment?->case_id ?? ($presetCase ?? 0));
    $selectedUser = old('user_id', $appointment?->user_id ?? auth()->id());
    $selectedDate = old('date', $appointment?->starts_at?->toDateString() ?? ($day ?? now())->toDateString());
    $selectedTime = old('time', $appointment?->starts_at?->format('H:i'));
@endphp

{{-- ═══ صاحبُ الموعد ═══

     أكثرُ المواعيد الأولى مع من لا ملفَّ له بعد: يتّصل ويطلب استشارة.
     فبابان: موكّلٌ من السجلّ، أو شخصٌ باسمه ورقمه — والرقمُ وحده يكفي
     لتصله رسالةُ التأكيد. --}}
<div class="mb-4 rounded-xl border border-gray-200 overflow-hidden"
     x-data="{ mode: '{{ $selectedClient ? 'client' : (old('guest_name', $appointment?->guest_name) ? 'guest' : 'client') }}' }">
    <div class="flex text-xs font-bold">
        <button type="button" x-on:click="mode = 'client'"
                :class="mode === 'client' ? 'bg-gold text-white' : 'bg-gray-50 text-gray-500'"
                class="flex-1 py-2.5 transition">موكّل مسجَّل</button>
        <button type="button" x-on:click="mode = 'guest'"
                :class="mode === 'guest' ? 'bg-gold text-white' : 'bg-gray-50 text-gray-500'"
                class="flex-1 py-2.5 transition">شخص جديد</button>
    </div>

    <div class="p-4">
        <div x-show="mode === 'client'">
            <label class="block text-xs font-semibold text-gray-600 mb-1" for="client_id">الموكّل</label>
            <select id="client_id" name="client_id" x-bind:disabled="mode !== 'client'"
                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
                <option value="">اختر الموكّل…</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected($selectedClient == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="mode === 'guest'" x-cloak class="grid md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="guest_name">اسم الشخص</label>
                <input id="guest_name" name="guest_name" maxlength="190"
                       value="{{ old('guest_name', $appointment?->guest_name) }}"
                       x-bind:disabled="mode !== 'guest'"
                       placeholder="سالم بن علي"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="guest_phone">رقم الهاتف</label>
                <input id="guest_phone" name="guest_phone" type="tel" inputmode="tel" dir="ltr" maxlength="40"
                       value="{{ old('guest_phone', $appointment?->guest_phone) }}"
                       x-bind:disabled="mode !== 'guest'"
                       placeholder="96891234567"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1" for="guest_email">البريد (اختياري)</label>
                <input id="guest_email" name="guest_email" type="email" maxlength="190" dir="ltr"
                       value="{{ old('guest_email', $appointment?->guest_email) }}"
                       x-bind:disabled="mode !== 'guest'"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            </div>
            <p class="md:col-span-3 text-[11px] text-gray-500">
                يصله تأكيدُ الموعد على واتساب مباشرةً — ولا يُضاف إلى سجلّ الموكّلين.
            </p>
        </div>

        @error('client_id')<p class="text-[11px] text-red-600 mt-2">{{ $message }}</p>@enderror
        @error('guest_phone')<p class="text-[11px] text-red-600 mt-2">{{ $message }}</p>@enderror
    </div>
</div>

<div class="grid md:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="case_id">القضية (اختياري)</label>
        <select id="case_id" name="case_id" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            <option value="">بلا قضية — استشارة</option>
            @foreach($cases as $case)
                <option value="{{ $case->id }}" @selected($selectedCase == $case->id)>
                    {{ $case->case_number }} — {{ \Illuminate\Support\Str::limit($case->title, 40) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="title">موضوع الموعد <span class="text-red-500">*</span></label>
        <input id="title" name="title" required maxlength="190" value="{{ old('title', $appointment?->title) }}"
               placeholder="استشارة أولى · توقيع وكالة · مراجعة مستندات"
               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
        @error('title')<p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="user_id">مع مَن</label>
        <select id="user_id" name="user_id" data-appt-user class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            <option value="">غير محدَّد</option>
            @foreach($staff as $member)
                <option value="{{ $member->id }}" @selected($selectedUser == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="minutes">المدّة (دقيقة)</label>
        <input id="minutes" name="minutes" type="number" min="5" max="480" step="5"
               value="{{ old('minutes', $appointment?->minutes ?? \App\Support\AppointmentSlots::slotMinutes()) }}"
               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="date">التاريخ <span class="text-red-500">*</span></label>
        <input id="date" name="date" type="date" required value="{{ $selectedDate }}" data-appt-date
               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
        @error('date')<p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="time">الوقت <span class="text-red-500">*</span></label>
        <input id="time" name="time" type="time" required value="{{ $selectedTime }}" data-appt-time
               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
        @error('time')<p class="text-[11px] text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- ═══ الفُسَحُ الشاغرة ═══
         تُجلب حيّةً عند كلّ تغيّرٍ ليوم أو موظّف: قائمةٌ مطبوعةٌ مع
         الصفحة تكذب بعد أوّل حجزٍ من زميلٍ في الغرفة الأخرى. --}}
    <div class="md:col-span-2" data-appt-slots
         data-url="{{ route('appointments.slots') }}"
         data-ignore="{{ $appointment?->id }}">
        <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs font-semibold text-gray-600">الأوقات المتاحة</label>
            <span class="text-[11px] text-gray-400" data-appt-legend></span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 min-h-[4rem]" data-appt-slots-box>
            <span class="text-[11px] text-gray-400">اختر التاريخ لعرض الأوقات المتاحة.</span>
        </div>

        <p class="text-[11px] text-gray-500 mt-1.5">
            أوقاتُ الدوام تُضبط من: الإعدادات ← المواعيد. والوقتُ المحجوز لدى الموظّف نفسِه يُرفض عند الحفظ.
        </p>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="location">المكان</label>
        <input id="location" name="location" maxlength="190" value="{{ old('location', $appointment?->location) }}"
               placeholder="مكتب المحاماة · اتصال هاتفي · مقرّ العميل"
               class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-600 mb-1" for="notes">ملاحظات داخلية</label>
        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                  class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">{{ old('notes', $appointment?->notes) }}</textarea>
        <p class="text-[11px] text-gray-500 mt-1">لا تُرسَل إلى الموكّل — للمكتب وحده.</p>
    </div>
</div>

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    var wrap = document.querySelector('[data-appt-slots]');
    if (!wrap) { return; }

    var box = wrap.querySelector('[data-appt-slots-box]');
    var legend = wrap.querySelector('[data-appt-legend]');
    var date = document.querySelector('[data-appt-date]');
    var user = document.querySelector('[data-appt-user]');
    var time = document.querySelector('[data-appt-time]');

    function clear() { while (box.firstChild) { box.removeChild(box.firstChild); } }

    function note(text, tone) {
        var p = document.createElement('p');
        p.className = 'text-[11px] ' + (tone === 'bad' ? 'text-red-600'
            : tone === 'warn' ? 'text-amber-700' : 'text-gray-400');
        p.textContent = text;
        box.appendChild(p);
        return p;
    }

    // ═══ شبكةٌ مرتّبةٌ لا رقائقُ متناثرة ═══
    //
    // الأوقاتُ أرقامٌ لاتينية: في سطرٍ عربيّ متدفّقٍ كانت تُقرأ من
    // اليمين فتبدو غيرَ مرتّبة. شبكةٌ ثابتةُ الأعمدة بـdir=ltr تعيد
    // الترتيبَ الطبيعيّ، وعنوانان يفصلان الصباحَ عن بعد الظهر.
    function group(title, slots) {
        if (!slots.length) { return; }

        var head = document.createElement('div');
        head.className = 'text-[10px] font-bold text-gray-400 mb-1.5 mt-2 first:mt-0';
        head.textContent = title;
        box.appendChild(head);

        var grid = document.createElement('div');
        grid.className = 'grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-1.5 mb-1';
        grid.setAttribute('dir', 'ltr');

        slots.forEach(function (slot) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = slot.time;
            b.disabled = !slot.free;
            b.title = slot.state === 'busy' ? 'محجوز' : (slot.state === 'past' ? 'مضى' : 'متاح');
            b.className = 'text-[11px] py-1.5 rounded-lg border text-center transition ' + (
                slot.state === 'free' ? 'bg-white text-gray-700 border-gray-200 hover:border-gold hover:text-gold-dark cursor-pointer'
                : slot.state === 'busy' ? 'bg-red-50 text-red-400 border-red-100 line-through cursor-not-allowed'
                : 'bg-gray-100 text-gray-300 border-gray-100 cursor-not-allowed');

            if (slot.free) {
                b.addEventListener('click', function () {
                    time.value = slot.time;
                    box.querySelectorAll('button').forEach(function (x) {
                        x.classList.remove('ring-2', 'ring-gold', 'bg-gold/10');
                    });
                    b.classList.add('ring-2', 'ring-gold', 'bg-gold/10');
                });
            }

            grid.appendChild(b);
        });

        box.appendChild(grid);
    }

    function load() {
        if (!date.value) { return; }

        var url = wrap.dataset.url + '?day=' + encodeURIComponent(date.value)
            + '&user_id=' + encodeURIComponent(user ? user.value : '')
            + '&ignore=' + encodeURIComponent(wrap.dataset.ignore || '');

        clear();
        legend.textContent = '';
        note('جارٍ الفحص…');

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                clear();

                if (!data.workday) {
                    note('هذا اليوم خارج أيّام العمل المضبوطة — يمكن الحجز فيه يدوياً بكتابة الوقت.', 'warn');
                    return;
                }

                if (!data.slots.length) {
                    note('لا فُسَح في هذا اليوم.');
                    return;
                }

                // ═══ يومٌ انقضى دوامُه ═══
                //
                // كان يُعرض صفّاً مشطوباً كأنّ المكتبَ محجوزٌ بالكامل،
                // فيظنّ الموظّفُ الشاشةَ معطّلة. يُقال السببُ، ويُعرض
                // أقربُ يومٍ فيه فُسحةٌ زراً يُنقر.
                if (!data.has_free) {
                    var allPast = data.slots.every(function (s) { return s.state === 'past'; });
                    note(allPast ? 'انقضى دوامُ هذا اليوم.' : 'كلُّ فُسَح هذا اليوم محجوزة.', 'warn');

                    if (data.next_open) {
                        var jump = document.createElement('button');
                        jump.type = 'button';
                        jump.className = 'mt-2 text-[11px] font-bold px-3 py-1.5 rounded-lg bg-gold text-white hover:bg-gold-dark';
                        jump.textContent = 'أقرب موعد: ' + data.next_open.label + ' — ' + data.next_open.time;
                        jump.addEventListener('click', function () {
                            date.value = data.next_open.date;
                            time.value = data.next_open.time;
                            load();
                        });
                        box.appendChild(jump);
                    }

                    return;
                }

                var freeCount = data.slots.filter(function (s) { return s.free; }).length;
                legend.textContent = freeCount + ' فُسحة متاحة';

                group('صباحاً', data.slots.filter(function (s) { return parseInt(s.time, 10) < 12; }));
                group('بعد الظهر', data.slots.filter(function (s) { return parseInt(s.time, 10) >= 12; }));
            })
            .catch(function () {
                clear();
                note('تعذّر جلب الأوقات — اكتب الوقت يدوياً.', 'bad');
            });
    }

    date.addEventListener('change', load);
    if (user) { user.addEventListener('change', load); }
    load();
});
</script>
@endpush
