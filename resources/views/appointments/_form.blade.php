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
        <label class="block text-xs font-semibold text-gray-600 mb-1">الأوقات المتاحة</label>
        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 min-h-[3.5rem] flex flex-wrap gap-1.5"
             data-appt-slots-box>
            <span class="text-[11px] text-gray-400">اختر التاريخ لعرض الأوقات المتاحة.</span>
        </div>
        <p class="text-[11px] text-gray-500 mt-1">
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
    var date = document.querySelector('[data-appt-date]');
    var user = document.querySelector('[data-appt-user]');
    var time = document.querySelector('[data-appt-time]');

    function load() {
        if (!date.value) { return; }

        var url = wrap.dataset.url + '?day=' + encodeURIComponent(date.value)
            + '&user_id=' + encodeURIComponent(user ? user.value : '')
            + '&ignore=' + encodeURIComponent(wrap.dataset.ignore || '');

        box.textContent = '';
        var wait = document.createElement('span');
        wait.className = 'text-[11px] text-gray-400';
        wait.textContent = 'جارٍ الفحص…';
        box.appendChild(wait);

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                box.textContent = '';

                if (!data.workday) {
                    var off = document.createElement('span');
                    off.className = 'text-[11px] text-amber-700';
                    off.textContent = 'هذا اليوم خارج أيّام العمل المضبوطة — يمكن الحجز فيه يدوياً بكتابة الوقت.';
                    box.appendChild(off);
                    return;
                }

                if (!data.slots.length) {
                    var none = document.createElement('span');
                    none.className = 'text-[11px] text-gray-400';
                    none.textContent = 'لا فُسَح في هذا اليوم.';
                    box.appendChild(none);
                    return;
                }

                data.slots.forEach(function (slot) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = slot.time;
                    b.disabled = !slot.free;
                    b.className = 'text-[11px] px-2.5 py-1.5 rounded-lg border ' + (slot.free
                        ? 'bg-white text-gray-700 border-gray-200 hover:border-gold hover:text-gold-dark cursor-pointer'
                        : 'bg-gray-100 text-gray-400 border-gray-100 line-through cursor-not-allowed');
                    if (slot.free) {
                        b.addEventListener('click', function () {
                            time.value = slot.time;
                            box.querySelectorAll('button').forEach(function (x) { x.classList.remove('ring-2', 'ring-gold'); });
                            b.classList.add('ring-2', 'ring-gold');
                        });
                    }
                    box.appendChild(b);
                });
            })
            .catch(function () {
                box.textContent = '';
                var bad = document.createElement('span');
                bad.className = 'text-[11px] text-red-600';
                bad.textContent = 'تعذّر جلب الأوقات — اكتب الوقت يدوياً.';
                box.appendChild(bad);
            });
    }

    date.addEventListener('change', load);
    if (user) { user.addEventListener('change', load); }
    load();
});
</script>
@endpush
