@extends('layouts.app')

@section('title', __('app.hr'))

@php $isAdmin = in_array(auth()->user()->role, ['developer', 'admin']); @endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-heading font-bold text-white">{{ __('app.hr') }}</h1>
</div>

@if($isAdmin)
{{-- Stats --}}
<div class="grid grid-cols-5 gap-4 mb-6">
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
    <div class="stat-card rounded-xl px-5 py-4">
        <p class="text-xs text-white/40 mb-1">إجازات pending</p>
        <p class="text-2xl font-bold text-yellow-400">{{ $stats['pending_leaves'] }}</p>
    </div>
</div>

{{-- Chart --}}
@if($tab === 'employees' && count($chartData) > 0)
<div class="card-premium rounded-xl p-6 mb-6">
    <h3 class="text-sm font-bold text-gold mb-4">تحليل أداء الموظفين</h3>
    <div class="grid grid-cols-2 gap-6">
        <div><canvas id="hrCasesChart"></canvas></div>
        <div><canvas id="hrRatingChart"></canvas></div>
    </div>
</div>
@endif
@endif

{{-- Tabs --}}
<div class="mb-6 border-b border-ivory/5 flex gap-1 overflow-x-auto">
    @if($isAdmin)
    <a href="{{ route('hr.index', ['tab' => 'employees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'employees' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">الموظفون</a>
    @endif
    <a href="{{ route('hr.index', ['tab' => 'performance']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'performance' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">التقييمات</a>
    <a href="{{ route('hr.index', ['tab' => 'bonuses']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'bonuses' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">المكافآت</a>
    <a href="{{ route('hr.index', ['tab' => 'penalties']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'penalties' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">الجزاءات</a>
    <a href="{{ route('hr.index', ['tab' => 'leaves']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'leaves' ? 'text-gold bg-gold/5 border-b-2 border-gold' : 'text-white/50 hover:text-white/70' }}">الإجازات</a>
</div>

{{-- Tab Content --}}
<div class="card-premium rounded-xl">
    @if($tab === 'employees' && $isAdmin)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الاسم</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">البريد</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الدور</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">قضايا</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">مهام</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التقييم</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الحالة</th></tr></thead>
                <tbody>
                    @foreach($employees as $emp)
                        @php $d = collect($chartData)->firstWhere('name', $emp->name) ?? ['cases'=>0,'tasks'=>0,'tasks_done'=>0,'rating'=>0]; @endphp
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $emp->name }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $emp->email }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full bg-gold/10 text-gold">{{ $emp->role }}</span></td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $d['cases'] }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $d['tasks_done'] }}/{{ $d['tasks'] }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $d['rating'] >= 4 ? 'bg-green-500/10 text-green-400' : ($d['rating'] >= 3 ? 'bg-yellow-500/10 text-yellow-400' : 'bg-white/10 text-white/40') }}">{{ $d['rating'] }}</span></td>
                            <td class="px-4 py-3">@if($emp->is_active)<span class="text-xs text-green-400">نشط</span>@else<span class="text-xs text-red-400">غير نشط</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    @elseif($tab === 'performance')
        @if($isAdmin)
        <div class="p-4 border-b border-ivory/5" x-data="{ open: false }">
            <button @click="open = !open" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold" x-text="open ? 'إلغاء' : '+ إضافة تقييم'"></button>
            <form x-show="open" x-cloak method="POST" action="{{ route('hr.performance.store') }}" class="mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="employee_id" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required><option value="">الموظف</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                    <input type="date" name="review_date" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <select name="rating" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required><option value="">التقييم</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} نجوم</option>@endfor</select>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <textarea name="notes" rows="2" placeholder="ملاحظات..." class="mt-3 w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white"></textarea>
            </form>
        </div>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التقييم</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المقيم</th>@if($isAdmin)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach($performances as $p)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $p->employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->review_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs font-bold {{ $p->rating >= 4 ? 'bg-green-500/10 text-green-400' : ($p->rating >= 3 ? 'bg-yellow-500/10 text-yellow-400' : 'bg-red-500/10 text-red-400') }}">{{ $p->rating }}/5</span></td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->reviewer->name }}</td>
                            @if($isAdmin)
                            <td class="px-4 py-3"><form method="POST" action="{{ route('hr.performance.destroy', $p) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                            @endif
                        </tr>
                        @if($p->notes)<tr class="border-b border-ivory/5"><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 pb-3 text-xs text-white/30">{{ $p->notes }}</td></tr>@endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $performances->appends(['tab' => 'performance'])->links() }}</div>

    @elseif($tab === 'bonuses')
        @if($isAdmin)
        <div class="p-4 border-b border-ivory/5" x-data="{ open: false }">
            <button @click="open = !open" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold" x-text="open ? 'إلغاء' : '+ إضافة مكافأة'"></button>
            <form x-show="open" x-cloak method="POST" action="{{ route('hr.bonuses.store') }}" class="mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="employee_id" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required><option value="">الموظف</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="date" name="date" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <input type="text" name="reason" placeholder="السبب" class="mt-3 w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
            </form>
        </div>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">السبب</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">بواسطة</th>@if($isAdmin)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach($bonuses as $b)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $b->employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-green-400 font-bold">{{ number_format($b->amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $b->reason }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $b->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $b->giver->name }}</td>
                            @if($isAdmin)
                            <td class="px-4 py-3"><form method="POST" action="{{ route('hr.bonuses.destroy', $b) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $bonuses->appends(['tab' => 'bonuses'])->links() }}</div>

    @elseif($tab === 'penalties')
        @if($isAdmin)
        <div class="p-4 border-b border-ivory/5" x-data="{ open: false }">
            <button @click="open = !open" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold" x-text="open ? 'إلغاء' : '+ إضافة جزاء'"></button>
            <form x-show="open" x-cloak method="POST" action="{{ route('hr.penalties.store') }}" class="mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-4 gap-4">
                    <select name="employee_id" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required><option value="">الموظف</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                    <input type="number" step="0.001" name="amount" placeholder="المبلغ (اختياري)" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                    <input type="date" name="date" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
                <input type="text" name="reason" placeholder="السبب" class="mt-3 w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
            </form>
        </div>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">المبلغ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">السبب</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">التاريخ</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">بواسطة</th>@if($isAdmin)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach($penalties as $p)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $p->employee->name }}</td>
                            <td class="px-4 py-3 text-sm text-red-400 font-bold">{{ $p->amount ? number_format($p->amount, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->reason }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $p->giver->name }}</td>
                            @if($isAdmin)
                            <td class="px-4 py-3"><form method="POST" action="{{ route('hr.penalties.destroy', $p) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-red-400 hover:text-red-300 text-xs">حذف</button></form></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $penalties->appends(['tab' => 'penalties'])->links() }}</div>

    @elseif($tab === 'leaves')
        <div class="p-4 border-b border-ivory/5" x-data="{ open: false }">
            <button @click="open = !open" class="btn-gold px-4 py-2 rounded-xl text-sm font-bold" x-text="open ? 'إلغاء' : '+ طلب إجازة'"></button>
            <form x-show="open" x-cloak method="POST" action="{{ route('hr.leaves.store') }}" class="mt-4 p-4 bg-white/5 rounded-xl">
                @csrf
                <div class="grid grid-cols-{{ $isAdmin ? 4 : 3 }} gap-4">
                    @if($isAdmin)
                    <select name="employee_id" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required><option value="">الموظف</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                    @else
                    <input type="hidden" name="employee_id" value="{{ auth()->id() }}">
                    @endif
                    <select name="type" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required><option value="">النوع</option><option value="annual">سنوية</option><option value="sick">مرضية</option><option value="emergency">طارئة</option><option value="maternity">أمومة</option><option value="unpaid">بدون راتب</option><option value="other">أخرى</option></select>
                    <input type="date" name="start_date" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                    <input type="date" name="end_date" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white" required>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <input type="text" name="reason" placeholder="السبب" class="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white">
                    <button type="submit" class="btn-gold rounded-xl text-sm font-bold">حفظ</button>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-ivory/5"><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الموظف</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">النوع</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">من</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">إلى</th><th class="text-right px-4 py-3 text-xs font-bold text-white/40">الحالة</th>@if($isAdmin)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach($leaves as $l)
                        <tr class="border-b border-ivory/5 hover:bg-white/[0.02]">
                            <td class="px-4 py-3 text-sm text-white/70">{{ $l->employee->name }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full bg-white/10 text-white/50">{{ $l->type }}</span></td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $l->start_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-white/50">{{ $l->end_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3"><span class="text-xs px-2 py-1 rounded-full {{ $l->status === 'approved' ? 'bg-green-500/10 text-green-400' : ($l->status === 'rejected' ? 'bg-red-500/10 text-red-400' : 'bg-yellow-500/10 text-yellow-400') }}">{{ $l->status === 'approved' ? 'معتمدة' : ($l->status === 'rejected' ? 'مرفوضة' : 'قيد الانتظار') }}</span></td>
                            @if($isAdmin)
                            <td class="px-4 py-3 flex gap-2">
                                @if($l->status === 'pending')
                                    <form method="POST" action="{{ route('hr.leaves.approve', $l) }}" class="inline">@csrf<button class="text-green-400 hover:text-green-300 text-xs">موافقة</button></form>
                                    <form method="POST" action="{{ route('hr.leaves.reject', $l) }}" class="inline">@csrf<button class="text-red-400 hover:text-red-300 text-xs">رفض</button></form>
                                @endif
                                <form method="POST" action="{{ route('hr.leaves.destroy', $l) }}" onsubmit="return confirm('حذف؟')">@csrf @method('DELETE')<button class="text-white/30 hover:text-red-400 text-xs">حذف</button></form>
                            </td>
                            @endif
                        </tr>
                        @if($l->reason)<tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 pb-3 text-xs text-white/30">{{ $l->reason }}</td></tr>@endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $leaves->appends(['tab' => 'leaves'])->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
@if($tab === 'employees' && $isAdmin && count($chartData) > 0)
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const names = {!! json_encode(array_column($chartData, 'name')) !!};
    const cases = {!! json_encode(array_column($chartData, 'cases')) !!};
    const tasks = {!! json_encode(array_column($chartData, 'tasks')) !!};
    const ratings = {!! json_encode(array_column($chartData, 'rating')) !!};

    new Chart(document.getElementById('hrCasesChart'), {
        type: 'bar',
        data: {
            labels: names,
            datasets: [
                { label: 'قضايا', data: cases, backgroundColor: '#C9A55A', borderRadius: 4 },
                { label: 'مهام', data: tasks, backgroundColor: '#60A5FA', borderRadius: 4 }
            ]
        },
        options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } }, scales: { y: { beginAtZero: true, ticks: { color: '#fff' } }, x: { ticks: { color: '#fff' } } } }
    });

    new Chart(document.getElementById('hrRatingChart'), {
        type: 'radar',
        data: {
            labels: names,
            datasets: [{ label: 'التقييم', data: ratings, backgroundColor: 'rgba(201,165,90,0.2)', borderColor: '#C9A55A', pointBackgroundColor: '#C9A55A' }]
        },
        options: { responsive: true, plugins: { legend: { labels: { color: '#fff' } } }, scales: { r: { beginAtZero: true, max: 5, ticks: { color: '#fff' }, grid: { color: 'rgba(255,255,255,0.1)' }, pointLabels: { color: '#fff' } } } }
    });
});
</script>
@endif
@endpush
