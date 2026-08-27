@extends('layouts.app')
@section('title', 'الرواتب')

@section('content')
@php $money = fn($v) => number_format((float) $v, 2); @endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="font-heading text-2xl font-bold">الرواتب</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted, #6B7280);">
            هذه الصفحة لإدارة المكتب فقط. لا يراها الموظف ولا تصله أرقامها.
        </p>
    </div>
    <form method="GET" class="flex items-end gap-2">
        <div>
            <label class="block text-xs mb-1" style="color: var(--text-muted, #6B7280);">الفترة</label>
            <input type="month" name="period" value="{{ $period }}"
                   class="rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
        </div>
        <button class="px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:#1F2937;">عرض</button>
    </form>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    @foreach([
        ['الإجمالي قبل الخصم', $money($totals['gross']) . ' ر.ع', '#111827'],
        ['الخصومات', $money($totals['deductions']) . ' ر.ع', '#EF4444'],
        ['الصافي', $money($totals['net']) . ' ر.ع', '#10B981'],
        ['بلا راتب مُسجَّل', $totals['without_salary'], '#D4AF37'],
    ] as [$label, $value, $color])
        <div class="rounded-2xl p-4" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
            <p class="text-xs" style="color: var(--text-muted, #6B7280);">{{ $label }}</p>
            <p class="text-xl font-bold mt-1" dir="ltr" style="color: {{ $color }};">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="rounded-2xl overflow-hidden mb-6" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--border, #E4DFD4);">
                    @foreach(['الموظف','الأساسي','البدلات','أيام إجازة خاصمة','خصم الإجازة','خصومات أخرى','الصافي',''] as $h)
                        <th class="text-start px-4 py-3 font-semibold text-xs" style="color:#B08D2E;">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($payslips as $p)
                    <tr style="border-bottom: 1px solid var(--border, #EFEAE0);">
                        <td class="px-4 py-3 font-medium">{{ $p['employee']->name }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $p['has_salary'] ? $money($p['basic']) : '—' }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $money($p['allowances']) }}</td>
                        <td class="px-4 py-3" dir="ltr">{{ $p['unpaid_days'] ?: '—' }}</td>
                        <td class="px-4 py-3" dir="ltr" style="color:{{ $p['leave_deduction'] > 0 ? '#EF4444' : 'inherit' }};">
                            {{ $p['leave_deduction'] > 0 ? $money($p['leave_deduction']) : '—' }}
                        </td>
                        <td class="px-4 py-3" dir="ltr">{{ $p['other_deductions'] > 0 ? $money($p['other_deductions']) : '—' }}</td>
                        <td class="px-4 py-3 font-bold" dir="ltr">{{ $p['has_salary'] ? $money($p['net']) : '—' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('salaries.show', $p['employee']) }}?period={{ $period }}"
                               class="text-xs font-semibold" style="color:#B08D2E;">الكشف</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-4">
    <div class="rounded-2xl p-5" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
        <h2 class="font-semibold mb-4">تحديد راتب موظف</h2>
        <form method="POST" action="{{ route('salaries.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs mb-1" style="color: var(--text-muted,#6B7280);">الموظف</label>
                <select name="employee_id" required class="w-full rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
                    @foreach($employees as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted,#6B7280);">الراتب الأساسي (ر.ع)</label>
                    <input type="number" step="0.01" min="0" name="basic_salary" required
                           class="w-full rounded-xl px-3 py-2 text-sm" dir="ltr" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted,#6B7280);">البدلات الثابتة</label>
                    <input type="number" step="0.01" min="0" name="allowances" value="0"
                           class="w-full rounded-xl px-3 py-2 text-sm" dir="ltr" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
                </div>
            </div>
            <div>
                <label class="block text-xs mb-1" style="color: var(--text-muted,#6B7280);">ملاحظة</label>
                <input type="text" name="note" maxlength="255"
                       class="w-full rounded-xl px-3 py-2 text-sm" style="border:1px solid var(--border,#E4DFD4); background:transparent;">
            </div>
            <button class="w-full py-2.5 rounded-xl text-sm font-semibold text-white" style="background:#1F2937;">حفظ الراتب</button>
        </form>
    </div>

    <div class="rounded-2xl p-5" style="background: var(--surface); border: 1px solid var(--border, #E4DFD4);">
        <h2 class="font-semibold mb-4">طريقة حساب اليوم</h2>
        <form method="POST" action="{{ route('salaries.settings') }}" class="space-y-3">
            @csrf
            <p class="text-xs mb-2" style="color: var(--text-muted,#6B7280);">
                قيمة اليوم = الراتب الأساسي ÷ عدد أيام الشهر. اختر ما يوافق سياسة مكتبك.
            </p>
            <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer" style="border:1px solid var(--border,#E4DFD4);">
                <input type="radio" name="hr_month_days_mode" value="fixed30" @checked($monthDaysMode === 'fixed30')>
                <span class="text-sm">شهر ثابت — ٣٠ يومًا دائمًا</span>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer" style="border:1px solid var(--border,#E4DFD4);">
                <input type="radio" name="hr_month_days_mode" value="actual" @checked($monthDaysMode === 'actual')>
                <span class="text-sm">أيام الشهر الفعلية (٢٨–٣١)</span>
            </label>
            <button class="w-full py-2.5 rounded-xl text-sm font-semibold" style="border:1px solid #B08D2E; color:#B08D2E;">حفظ الطريقة</button>
        </form>
        <p class="text-[11px] mt-4 leading-relaxed" style="color: var(--text-muted,#6B7280);">
            هذه الأرقام لإدارة المكتب داخليًا، ولا تُغني عن المتطلبات المحاسبية أو القانونية الرسمية.
        </p>
    </div>
</div>
@endsection
