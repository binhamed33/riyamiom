@extends('layouts.app')
@section('title', 'كشف راتب')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('hr.index', ['tab' => 'salaries', 'period' => $period]) }}"
           class="text-xs text-gold-dark hover:underline">← الرواتب</a>
        <h1 class="font-heading text-2xl font-bold text-gray-700 mt-2">كشف راتب — {{ $employee->name }}</h1>
        <p class="text-sm text-gray-400 mt-1" dir="ltr">{{ $period }}</p>
    </div>

    <div class="bg-white rounded-xl border border-gold/15 p-6 mb-6">
        @if(! $payslip['has_salary'])
            <p class="text-sm text-gold-dark bg-gold/10 border border-gold/20 rounded-lg px-4 py-3 mb-5">
                لم يُسجَّل راتب لهذا الموظف بعد — الأرقام أدناه أصفار حتى يُسجَّل.
            </p>
        @endif

        @foreach([
            ['الراتب الأساسي', $payslip['basic'], false],
            ['البدلات', $payslip['allowances'], false],
            ['خصم إجازة (' . $payslip['unpaid_days'] . ' يوم)', $payslip['leave_deduction'], true],
            ['خصومات أخرى', $payslip['other_deductions'], true],
        ] as [$label, $value, $isDeduction])
            <div class="flex items-center justify-between py-3 border-b border-gray-200">
                <span class="text-sm text-gray-400">{{ $label }}</span>
                <span class="text-sm font-semibold {{ $isDeduction && $value > 0 ? 'text-red-500' : 'text-gray-700' }}" dir="ltr">
                    {{ $isDeduction && $value > 0 ? '−' : '' }}{{ number_format($value, 2) }} ر.ع
                </span>
            </div>
        @endforeach

        <div class="flex items-center justify-between pt-5">
            <span class="font-bold text-gray-700">الصافي</span>
            <span class="text-2xl font-bold text-emerald-500" dir="ltr">{{ number_format($payslip['net'], 2) }} ر.ع</span>
        </div>

        <p class="text-[11px] text-gray-400 mt-4">
            قيمة اليوم <span dir="ltr">{{ number_format($payslip['daily_rate'], 2) }}</span> ر.ع
            (الأساسي ÷ {{ $payslip['month_days'] }} يومًا).
        </p>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">بنود الفترة</h2>
            @forelse($payslip['adjustments'] as $a)
                <div class="flex items-center justify-between py-2.5 border-b border-gray-200 last:border-0">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-700 truncate">{{ $a->reason }}</p>
                        <p class="text-[11px] text-gray-400">{{ $a->kind === 'allowance' ? 'بدل' : 'خصم' }}</p>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="text-sm font-semibold {{ $a->kind === 'deduction' ? 'text-red-500' : 'text-emerald-500' }}" dir="ltr">
                            {{ $a->kind === 'deduction' ? '−' : '+' }}{{ number_format($a->amount, 2) }}
                        </span>
                        <form method="POST" action="{{ route('salaries.adjustments.destroy', $a) }}">
                            @csrf @method('DELETE')
                            <button class="text-[11px] text-red-500 hover:underline">حذف</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 py-4">لا بنود في هذه الفترة.</p>
            @endforelse
        </div>

        <div class="bg-white rounded-xl border border-gold/15 p-6">
            <h2 class="text-sm font-bold text-gold-dark mb-4">إضافة بند</h2>
            <form method="POST" action="{{ route('salaries.adjustments.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <select name="kind" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                    <option value="allowance">بدل (يُضاف)</option>
                    <option value="deduction">خصم (يُطرح)</option>
                </select>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="المبلغ" dir="ltr"
                       class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                <input type="text" name="reason" required maxlength="255" placeholder="السبب"
                       class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                <button class="w-full bg-primary hover:bg-primary-dark text-white py-2.5 rounded-lg font-semibold text-sm transition-colors">إضافة</button>
            </form>
        </div>
    </div>
</div>
@endsection
