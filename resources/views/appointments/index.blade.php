@extends('layouts.app')

@section('title', 'المواعيد')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6" x-data>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">المواعيد</h1>
            <p class="text-xs text-gray-500 mt-1">
                لقاءاتُ المكتب مع الموكّلين — والموكّل يصله تأكيدٌ على واتساب والبريد.
                @if($todayCount > 0)
                    <span class="text-gold-dark font-semibold">اليوم: {{ $todayCount }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('appointments.create', ['day' => $day->toDateString()]) }}"
           class="text-xs font-bold px-4 py-2.5 rounded-xl bg-gold text-white hover:bg-gold-dark">+ حجز موعد</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm px-4 py-3">{{ session('error') }}</div>
    @endif

    {{-- المدى: القادم افتراضاً، ويومٌ بعينه، والماضي، وتقويمُ الأسبوع --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <a href="{{ route('appointments.index', array_filter(['view' => 'week', 'day' => $day->toDateString(), 'user_id' => request('user_id')])) }}"
           class="text-xs px-3 py-1.5 rounded-lg border {{ $view === 'week' ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:border-gold/40' }}">
            🗓 تقويم الأسبوع
        </a>
        <span class="text-gray-200">|</span>

        @foreach(['upcoming' => 'القادمة', 'day' => 'يومٌ بعينه', 'past' => 'الماضية'] as $key => $label)
            <a href="{{ route('appointments.index', array_filter(['scope' => $key, 'day' => $key === 'day' ? $day->toDateString() : null])) }}"
               class="text-xs px-3 py-1.5 rounded-lg border {{ $scope === $key ? 'bg-gold text-white border-gold' : 'bg-white text-gray-600 border-gray-200 hover:border-gold/40' }}">
                {{ $label }}
            </a>
        @endforeach

        <form method="GET" action="{{ route('appointments.index') }}" class="flex items-center gap-2 ms-auto">
            <input type="hidden" name="scope" value="day">
            <input type="date" name="day" value="{{ $day->toDateString() }}" data-auto-submit
                   class="text-xs rounded-lg border border-gray-200 px-2 py-1.5">
            <select name="user_id" data-auto-submit class="text-xs rounded-lg border border-gray-200 px-2 py-1.5">
                <option value="">كل الموظفين</option>
                @foreach($staff as $member)
                    <option value="{{ $member->id }}" @selected(request('user_id') == $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($view === 'week' && $week !== [])
        {{-- ═══ شبكةُ الأسبوع ═══
             عمودٌ لكلّ يوم، وشريطُ امتلاءٍ تحت اسمه: نظرةٌ واحدةٌ تقول
             أيُّ يومٍ مزدحمٌ قبل أن يُفتح، بدل تقليب المواعيد واحداً واحداً. --}}
        <div class="mb-4 flex items-center justify-between gap-2">
            <a href="{{ route('appointments.index', ['view' => 'week', 'day' => $weekStart->copy()->subWeek()->toDateString(), 'user_id' => request('user_id')]) }}"
               class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-600 hover:border-gold/40">← الأسبوع السابق</a>
            <span class="text-xs font-bold text-gray-700">
                {{ $weekStart->locale('ar')->isoFormat('D MMMM') }} — {{ $weekStart->copy()->addDays(6)->locale('ar')->isoFormat('D MMMM YYYY') }}
            </span>
            <a href="{{ route('appointments.index', ['view' => 'week', 'day' => $weekStart->copy()->addWeek()->toDateString(), 'user_id' => request('user_id')]) }}"
               class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-600 hover:border-gold/40">الأسبوع التالي →</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2 mb-6">
            @foreach($week as $column)
                @php
                    $isToday = $column['date']->isToday();
                    $load = $column['load'];
                    $bar = $load >= 80 ? 'bg-red-400' : ($load >= 45 ? 'bg-amber-400' : 'bg-emerald-400');
                @endphp
                <div class="rounded-xl border {{ $isToday ? 'border-gold/50 bg-gold/5' : 'border-gray-200 bg-white' }} p-2.5 flex flex-col min-h-[9rem]">
                    <div class="flex items-baseline justify-between">
                        <span class="text-[11px] font-bold {{ $isToday ? 'text-gold-dark' : 'text-gray-700' }}">
                            {{ $column['date']->locale('ar')->isoFormat('ddd') }}
                        </span>
                        <span class="text-[11px] text-gray-400">{{ $column['date']->format('d/m') }}</span>
                    </div>

                    @if($column['workday'])
                        <div class="h-1 rounded-full bg-gray-100 mt-1.5 mb-2 overflow-hidden" title="الامتلاء {{ $load }}%">
                            <div class="h-full {{ $bar }}" style="width: {{ max(3, $load) }}%"></div>
                        </div>
                    @else
                        <div class="text-[10px] text-gray-400 mt-1.5 mb-2">عطلة</div>
                    @endif

                    <div class="flex-1 space-y-1">
                        @forelse($column['items'] as $item)
                            <a href="{{ route('appointments.edit', $item) }}"
                               class="block rounded-lg px-2 py-1 text-[10px] leading-tight border transition
                                      {{ $item->status === 'completed' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-900 hover:border-gold' }}">
                                <span class="font-bold">{{ $item->starts_at->format('H:i') }}</span>
                                <span class="block truncate">{{ $item->personName() }}</span>
                            </a>
                        @empty
                            @if($column['workday'])
                                <a href="{{ route('appointments.create', ['day' => $column['date']->toDateString()]) }}"
                                   class="block text-center text-[10px] text-gray-300 hover:text-gold-dark py-2">+ فارغ</a>
                            @endif
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @forelse($appointments as $appointment)
            @php
                $tone = match($appointment->status) {
                    'cancelled' => 'bg-gray-50 text-gray-500 border-gray-200',
                    'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'no_show'   => 'bg-red-50 text-red-700 border-red-200',
                    default     => 'bg-amber-50 text-amber-800 border-amber-200',
                };
            @endphp
            <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50/60">
                <div class="w-28 shrink-0">
                    <div class="text-sm font-bold text-gray-800">{{ $appointment->starts_at->format('H:i') }}</div>
                    <div class="text-[11px] text-gray-500">{{ $appointment->starts_at->locale('ar')->isoFormat('ddd D MMM') }}</div>
                </div>

                <div class="flex-1 min-w-[12rem]">
                    <div class="text-sm font-semibold text-gray-800">{{ $appointment->title }}</div>
                    <div class="text-[11px] text-gray-500 mt-0.5">
                        {{ $appointment->personName() }}@if($appointment->isGuest() && $appointment->guest_phone) <span class="text-gray-400" dir="ltr">{{ $appointment->guest_phone }}</span>@endif
                        @if($appointment->user) · مع {{ $appointment->user->name }} @endif
                        @if($appointment->case) · {{ $appointment->case->case_number }} @endif
                        @if($appointment->location) · {{ $appointment->location }} @endif
                        · {{ $appointment->minutes }} د
                    </div>
                </div>

                <span class="text-[11px] px-2 py-0.5 rounded-full border {{ $tone }}">{{ $appointment->statusLabel() }}</span>

                <div class="flex items-center gap-1">
                    @if($appointment->status === 'scheduled')
                        <form method="POST" action="{{ route('appointments.status', $appointment) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button class="text-[11px] px-2 py-1 rounded-lg border border-gray-200 text-gray-600 hover:border-emerald-300 hover:text-emerald-700">تمّ</button>
                        </form>
                    @endif
                    <a href="{{ route('appointments.edit', $appointment) }}"
                       class="text-[11px] px-2 py-1 rounded-lg border border-gray-200 text-gray-600 hover:border-gold/40">تعديل</a>
                </div>
            </div>
        @empty
            <div class="px-4 py-12 text-center text-sm text-gray-400">
                لا مواعيد في هذا المدى.
                <a href="{{ route('appointments.create') }}" class="text-gold-dark font-semibold">احجز موعداً</a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $appointments->links() }}</div>
</div>

@push('scripts')
{{-- المعالجُ يُربط هنا لا في السمة: سياسةُ CSP تمنع السكربتَ المضمّن --}}
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-auto-submit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form && el.form.submit(); });
    });
});
</script>
@endpush
@endsection
