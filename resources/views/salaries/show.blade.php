@extends('layouts.app')
@section('title', 'كشف راتب')

@section('content')
@php $money = fn($v) => number_format((float) $v, 2); @endphp

<div class="mb-6">
    <a href="{{ route('salaries.index') }}?period={{ $period }}" class="text-xs" style="color:#B08D2E;">← الرواتب</a>
    <h1 class="font-heading text-2xl font-bold mt-2">كشف راتب — {{ $employee->name }}</h1>
    <p class="text-sm mt-1" dir="ltr" style="color: var(--text-muted,#6B7280);">{{ $period }}</p>
</div>

<div class="rounded-2xl p-6 mb-6" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
    @if(! $payslip['has_salary'])
        <p class="text-sm mb-4 px-4 py-3 rounded-xl" style="background: rgba(212,175,55,.10); color:#B08D2E;">
            لم يُسجَّل راتب لهذا الموظف بعد — الأرقام أدناه أصفار حتى يُسجَّل.
        </p>
    @endif

    @foreach([
        ['الراتب الأساسي', $payslip['basic'], false],
        ['البدلات', $payslip['allowances'], false],
        ['خصم إجازة (' . $payslip['unpaid_days'] . ' يوم)', $payslip['leave_deduction'], true],
        ['خصومات أخرى', $payslip['other_deductions'], true],
    ] as [$label, $value, $isDeduction])
        <div class="flex items-center justify-between py-3" style="border-bottom: 1px solid var(--border, #EFEAE0);">
            <span class="text-sm" style="color: var(--text-muted,#6B7280);">{{ $label }}</span>
            <span class="text-sm font-semibold" dir="ltr" style="color: {{ $isDeduction && $value > 0 ? '#EF4444' : 'inherit' }};">
                {{ $isDeduction && $value > 0 ? '−' : '' }}{{ $money($value) }} ر.ع
            </span>
        </div>
    @endforeach

    <div class="flex items-center justify-between pt-4 mt-2">
        <span class="font-bold">الصافي</span>
        <span class="text-2xl font-bold" dir="ltr" style="color:#10B981;">{{ $money($payslip['net']) }} ر.ع</span>
    </div>

    <p class="text-[11px] mt-4" style="color: var(--text-muted,#6B7280);">
        قيمة اليوم <span dir="ltr">{{ $money($payslip['daily_rate']) }}</span> ر.ع
        (الأساسي ÷ {{ $payslip['month_days'] }} يومًا).
    </p>
</div>

<div class="grid lg:grid-cols-2 gap-4">
    <div class="rounded-2xl p-5" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
        <h2 class="font-semibold mb-4">بنود الفترة</h2>
        @forelse($payslip['adjustments'] as $a)
            <div class="flex items-center justify-between py-2.5" style="border-bottom: 1px solid var(--border,#EFEAE0);">
                <div class="min-w-0">
                    <p class="text-sm truncate">{{ $a->reason }}</p>
                    <p class="text-[11px]" style="color: var(--text-muted,#6B7280);">{{ $a->kind === 'allowance' ? 'بدل' : 'خصم' }}</p>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="text-sm font-semibold" dir="ltr" style="color: {{ $a->kind === 'deduction' ? '#EF4444' : '#10B981' }};">
                        {{ $a->kind === 'deduction' ? '−' : '+' }}{{ $money($a->amount) }}
                    </span>
                    <form method="POST" action="{{ route('salaries.adjustments.destroy', $a) }}">
                        @csrf @method('DELETE')
                        <button class="text-[11px]" style="color:#EF4444;">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm py-4" style="color: var(--text-muted,#6B7280);">لا بنود في هذه الفترة.</p>
        @endforelse
    </div>

    <div class="rounded-2xl p-5" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
        <h2 class="font-semibold mb-4">إضافة بند</h2>
        <form method="POST" action="{{ route('salaries.adjustments.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <select name="kind" class="w-full rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
                <option value="allowance">بدل (يُضاف)</option>
                <option value="deduction">خصم (يُطرح)</option>
            </select>
            <input type="number" step="0.01" min="0.01" name="amount" required placeholder="المبلغ" dir="ltr"
                   class="w-full rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
            <input type="text" name="reason" required maxlength="255" placeholder="السبب"
                   class="w-full rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
            <button class="w-full py-2.5 rounded-xl text-sm font-semibold text-white" style="background:#1F2937;">إضافة</button>
        </form>
    </div>
</div>
@endsection
