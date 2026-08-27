@extends('layouts.app')
@section('title', 'الحضور والانصراف')

@section('content')
@php
    $chip = [
        'present'   => ['label' => 'حاضر',   'dot' => '#10B981', 'bg' => 'rgba(16,185,129,.10)', 'fg' => '#0F766E'],
        'completed' => ['label' => 'منتهٍ',  'dot' => '#9CA3AF', 'bg' => 'rgba(156,163,175,.12)', 'fg' => '#4B5563'],
        'on_leave'  => ['label' => 'إجازة',  'dot' => '#D4AF37', 'bg' => 'rgba(212,175,55,.12)', 'fg' => '#B08D2E'],
        'absent'    => ['label' => 'غائب',   'dot' => '#E5E7EB', 'bg' => 'rgba(229,231,235,.35)', 'fg' => '#6B7280'],
    ];
    $fmt = fn($dt) => $dt ? $dt->timezone('Asia/Muscat')->format('h:i A') : '—';
    $dur = function ($m) {
        if ($m === null) return '—';
        return intdiv($m, 60) . 'س ' . ($m % 60) . 'د';
    };
@endphp

<div class="mb-6">
    <h1 class="font-heading text-2xl font-bold">الحضور والانصراف</h1>
    <p class="text-sm mt-1" style="color: var(--text-muted, #6B7280);">
        {{ $isManager ? 'حضور الفريق وسجلّه.' : 'سجلّ حضورك.' }}
    </p>
</div>

@if($isManager && $stats)
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    @foreach([
        ['حاضرون الآن', $stats['present'], '#10B981'],
        ['انصرفوا', $stats['completed'], '#6B7280'],
        ['في إجازة', $stats['on_leave'], '#D4AF37'],
        ['غائبون', $stats['absent'], '#EF4444'],
    ] as [$label, $value, $color])
        <div class="rounded-2xl p-4" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
            <p class="text-xs" style="color: var(--text-muted, #6B7280);">{{ $label }}</p>
            <p class="text-3xl font-bold mt-1" style="color: {{ $color }};">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="rounded-2xl p-4 mb-6" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
    <h2 class="font-semibold text-sm mb-3">حالة الفريق اليوم</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
        @foreach($board as $row)
            @php $c = $chip[$row['status']]; @endphp
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl" style="background: {{ $c['bg'] }};">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $c['dot'] }};"></span>
                <span class="text-sm font-medium truncate flex-1">{{ $row['employee']->name }}</span>
                <span class="text-[11px] font-semibold flex-shrink-0" style="color: {{ $c['fg'] }};">{{ $c['label'] }}</span>
                @if($row['record'])
                    <span class="text-[11px] flex-shrink-0" dir="ltr" style="color: var(--text-muted, #6B7280);">
                        {{ $fmt($row['record']->check_in_at) }}@if($row['record']->check_out_at) — {{ $fmt($row['record']->check_out_at) }}@endif
                    </span>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

<form method="GET" class="rounded-2xl p-4 mb-4 flex flex-wrap items-end gap-3"
      style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
    <div>
        <label class="block text-xs mb-1" style="color: var(--text-muted, #6B7280);">المدى</label>
        <select name="range" class="rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
            <option value="day" @selected($range === 'day')>اليوم</option>
            <option value="week" @selected($range === 'week')>الأسبوع</option>
            <option value="month" @selected($range === 'month')>الشهر</option>
        </select>
    </div>
    <div>
        <label class="block text-xs mb-1" style="color: var(--text-muted, #6B7280);">التاريخ</label>
        <input type="date" name="date" value="{{ $date->toDateString() }}"
               class="rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
    </div>
    @if($isManager)
    <div>
        <label class="block text-xs mb-1" style="color: var(--text-muted, #6B7280);">الموظف</label>
        <select name="employee_id" class="rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
            <option value="">الجميع</option>
            @foreach($employees as $e)
                <option value="{{ $e->id }}" @selected(($filters['employee_id'] ?? null) == $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div>
        <label class="block text-xs mb-1" style="color: var(--text-muted, #6B7280);">الحالة</label>
        <select name="status" class="rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
            <option value="">الكل</option>
            <option value="present" @selected(($filters['status'] ?? null) === 'present')>حاضر</option>
            <option value="completed" @selected(($filters['status'] ?? null) === 'completed')>منتهٍ</option>
        </select>
    </div>
    <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold text-white" style="background:#1F2937;">تصفية</button>
    <a href="{{ route('attendance.index') }}" class="px-4 py-2 rounded-xl text-sm" style="color: var(--text-muted,#6B7280);">مسح</a>
</form>

<div class="rounded-2xl overflow-hidden" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--border, #E4DFD4);">
                    <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">الموظف</th>
                    <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">التاريخ</th>
                    <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">الحضور</th>
                    <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">الانصراف</th>
                    <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">المدة</th>
                    <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $r)
                    @php $st = $r->check_out_at ? 'completed' : 'present'; $c = $chip[$st]; @endphp
                    <tr style="border-bottom: 1px solid var(--border, #EFEAE0);">
                        <td class="px-4 py-3">{{ $r->user->name ?? '—' }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $r->work_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $fmt($r->check_in_at) }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $r->check_out_at ? $fmt($r->check_out_at) : 'ما زال حاضرًا' }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $dur($r->minutes) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                                  style="background: {{ $c['bg'] }}; color: {{ $c['fg'] }};">
                                <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $c['dot'] }};"></span>
                                {{ $c['label'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center" style="color: var(--text-muted,#6B7280);">لا سجلات في هذا المدى.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $records->links() }}</div>
@endsection
