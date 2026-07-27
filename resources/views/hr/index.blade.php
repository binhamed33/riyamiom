@extends('layouts.app')

@section('title', 'الموارد البشرية')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-heading font-bold text-white">الموارد البشرية</h1>
</div>

{{-- Stats --}}
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">إجمالي الموظفين</p>
        <p class="text-2xl font-bold text-gold">{{ $stats['total_employees'] }}</p>
    </div>
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">متوسط التقييم</p>
        <p class="text-2xl font-bold text-gold">{{ number_format($stats['avg_rating'] ?? 0, 1) }}/5</p>
    </div>
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">إجمالي المكافآت</p>
        <p class="text-2xl font-bold text-green-400">{{ number_format($stats['total_bonuses'], 2) }} ر.ع</p>
    </div>
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">إجمالي الجزاءات</p>
        <p class="text-2xl font-bold text-red-400">{{ number_format($stats['total_penalties'], 2) }} ر.ع</p>
    </div>
</div>

{{-- Tabs --}}
<div class="mb-6 border-b border-ivory/5 flex gap-1">
    <a href="{{ route('hr.index', ['tab' => 'employees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'employees' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">الموظفون</a>
    <a href="{{ route('hr.index', ['tab' => 'performance']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'performance' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">التقييمات</a>
    <a href="{{ route('hr.index', ['tab' => 'bonuses']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'bonuses' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">المكافآت</a>
    <a href="{{ route('hr.index', ['tab' => 'penalties']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg {{ $tab === 'penalties' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">الجزاءات</a>
</div>

{{-- Tab Content --}}
<div class="card-premium rounded-xl">
    @if($tab === 'employees')
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-ivory/5">
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">الاسم</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">البريد</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">الدور</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $emp)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $emp->name }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $emp->email }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full bg-gold/10 text-gold">{{ $emp->role }}</span></td>
                            <td class="px-4 py-3">
                                @if($emp->is_active)
                                    <span class="text-xs text-green-400">نشط</span>
                                @else
                                    <span class="text-xs text-red-400">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @elseif($tab === 'performance')
        <div class="p-4 border-b border-ivory/5">
            <button onclick="document.getElementById('perfForm').classList.toggle('hidden')" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold">+ إضافة تقييم</button>
            <form id="perfForm" method="POST" action="{{ route('hr.performance.store') }}" class="hidden mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="employee_id" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="">الموظف</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="review_date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <select name="rating" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="">التقييم</option>
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }} نجوم</option>
                        @endfor
                    </select>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <textarea name="notes" rows="2" placeholder="ملاحظات..." class="mt-3 w-full form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white"></textarea>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-ivory/5">
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">التقييم</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40">المقيم</th>
                        <th class="text-right px-4 py-3 text-xs font-bold text-white/40"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($performances as $p)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $p->employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->review_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-bold {{ $p->rating >= 4 ? 'bg-green-500/10 text-green-400' : ($p->rating >= 3 ? 'bg-yellow-500/10 text-yellow-400' : 'bg-red-500/10 text-red-400') }}">
                                    {{ $p->rating }}/5
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->reviewer->name }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('hr.performance.destroy', $p) }}" class="inline" onsubmit="return confirm('حذف التقييم؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form>
                            </td>
                        </tr>
                        @if($p->notes)
                            <tr class="border-b border-ivory/5"><td colspan="5" class="px-4 pb-3 text-xs text-white/30">{{ $p->notes }}</td></tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $performances->appends(['tab' => 'performance'])->links() }}</div>

    @elseif($tab === 'bonuses')
        <div class="p-4 border-b border-ivory/5">
            <button onclick="document.getElementById('bonusForm').classList.toggle('hidden')" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold">+ إضافة مكافأة</button>
            <form id="bonusForm" method="POST" action="{{ route('hr.bonuses.store') }}" class="hidden mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="employee_id" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="">الموظف</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="date" name="date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <input type="text" name="reason" placeholder="السبب" class="mt-3 w-full form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">السبب</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">بواسطة</th><th></th></tr></thead>
                <tbody>
                    @foreach($bonuses as $b)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $b->employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-green-400 font-bold">{{ number_format($b->amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $b->reason }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $b->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $b->giver->name }}</td>
                            <td class="px-4 py-3"><form method="POST" action="{{ route('hr.bonuses.destroy', $b) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $bonuses->appends(['tab' => 'bonuses'])->links() }}</div>

    @elseif($tab === 'penalties')
        <div class="p-4 border-b border-ivory/5">
            <button onclick="document.getElementById('penaltyForm').classList.toggle('hidden')" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold">+ إضافة جزاء</button>
            <form id="penaltyForm" method="POST" action="{{ route('hr.penalties.store') }}" class="hidden mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="employee_id" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                        <option value="">الموظف</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ (اختياري)" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                    <input type="date" name="date" class="form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <input type="text" name="reason" placeholder="السبب" class="mt-3 w-full form-input bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">السبب</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">بواسطة</th><th></th></tr></thead>
                <tbody>
                    @foreach($penalties as $p)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $p->employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-red-400 font-bold">{{ $p->amount ? number_format($p->amount, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->reason }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->giver->name }}</td>
                            <td class="px-4 py-3"><form method="POST" action="{{ route('hr.penalties.destroy', $p) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $penalties->appends(['tab' => 'penalties'])->links() }}</div>
    @endif
</div>
@endsection
