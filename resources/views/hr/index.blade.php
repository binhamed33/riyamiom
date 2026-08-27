@extends('layouts.app')

@section('title', __('app.hr'))

@php $isAdmin = in_array(auth()->user()->role, ['developer', 'admin']); @endphp

@section('content')
<div class="">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gold-dark">{{ __('app.hr') }}</h1>
    </div>

    @if($isAdmin)
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجمالي الموظفين</p>
            <p class="text-2xl font-bold text-gold-dark">{{ $stats['total_employees'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">متوسط التقييم</p>
            <p class="text-2xl font-bold text-gold-dark">{{ number_format($stats['avg_rating'] ?? 0, 1) }}/5</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجمالي المكافآت</p>
            <p class="text-2xl font-bold text-green-700">{{ number_format($stats['total_bonuses'], 2) }} ر.ع</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجمالي الجزاءات</p>
            <p class="text-2xl font-bold text-red-700">{{ number_format($stats['total_penalties'], 2) }} ر.ع</p>
        </div>
        <div class="bg-white rounded-xl border border-gold/15 p-5">
            <p class="text-xs text-gray-400 mb-1">إجازات pending</p>
            <p class="text-2xl font-bold text-yellow-700">{{ $stats['pending_leaves'] }}</p>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200 flex gap-1 overflow-x-auto">
        @if($isAdmin)
        <a href="{{ route('hr.index', ['tab' => 'employees']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'employees' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الموظفون</a>
        @endif
        <a href="{{ route('hr.index', ['tab' => 'attendance']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'attendance' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الحضور</a>
        <a href="{{ route('hr.index', ['tab' => 'performance']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'performance' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">التقييمات</a>
        <a href="{{ route('hr.index', ['tab' => 'bonuses']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'bonuses' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">المكافآت</a>
        <a href="{{ route('hr.index', ['tab' => 'penalties']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'penalties' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الجزاءات</a>
        <a href="{{ route('hr.index', ['tab' => 'leaves']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'leaves' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الإجازات</a>
        {{-- سجلّ الحضور والرواتب هنا لا في صفحتين منفصلتين: كلاهما
             شأنٌ من شؤون الموظف، وتفريقهما في الشريط الجانبي جعل
             المستخدم يبحث عن راتبٍ في مكانٍ لا يخطر له. --}}
        <a href="{{ route('hr.index', ['tab' => 'attendance_log']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'attendance_log' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">سجلّ الحضور</a>
        @if($canManageSalaries)
        <a href="{{ route('hr.index', ['tab' => 'salaries']) }}" class="px-5 py-3 text-sm font-medium transition rounded-t-lg whitespace-nowrap {{ $tab === 'salaries' ? 'text-gold-dark bg-gray-100 border-b-2 border-gold' : 'text-gray-400 hover:text-gray-600' }}">الرواتب</a>
        @endif
    </div>

    {{-- Tab Content --}}
    @if($tab === 'attendance')
    <div class="space-y-6">
        {{-- بطاقة اليوم: زرّ واحد يقول الحقيقة عن حالتك الآن --}}
        <div class="bg-white rounded-xl border border-gold/15 p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-400 mb-1">{{ now('Asia/Muscat')->translatedFormat('l j F Y') }}</p>
                @if(!$attendanceToday)
                    <p class="text-lg font-bold text-gray-700">لم تسجّل حضورك اليوم بعد</p>
                @elseif($attendanceToday->check_out_at === null)
                    <p class="text-lg font-bold text-green-700">حاضر منذ {{ $attendanceToday->check_in_at->timezone('Asia/Muscat')->format('H:i') }}</p>
                @else
                    <p class="text-lg font-bold text-gray-700">
                        يوم مكتمل: {{ $attendanceToday->check_in_at->timezone('Asia/Muscat')->format('H:i') }}
                        — {{ $attendanceToday->check_out_at->timezone('Asia/Muscat')->format('H:i') }}
                        <span class="text-sm text-gray-400">({{ intdiv((int) $attendanceToday->minutes, 60) }}س {{ ((int) $attendanceToday->minutes) % 60 }}د)</span>
                    </p>
                @endif
                @error('attendance')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                @if(!$attendanceToday)
                    <form method="POST" action="{{ route('hr.attendance.checkin') }}">@csrf
                        <button class="px-6 py-3 rounded-xl bg-gold-dark text-white font-bold hover:opacity-90 transition md-touch">تسجيل الحضور</button>
                    </form>
                @elseif($attendanceToday->check_out_at === null)
                    <form method="POST" action="{{ route('hr.attendance.checkout') }}">@csrf
                        <button class="px-6 py-3 rounded-xl bg-gray-700 text-white font-bold hover:opacity-90 transition md-touch">تسجيل الانصراف</button>
                    </form>
                @endif
            </div>
        </div>

        @if($isAdmin)
        {{-- حضور الفريق اليوم — للإدارة وحدها --}}
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            <p class="px-4 pt-4 pb-2 font-bold text-gold-dark text-sm">حضور الفريق اليوم</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الحضور</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الانصراف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المدة</th></tr></thead>
                    <tbody>
                        @forelse($teamAttendance as $rec)
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3 font-medium">{{ $rec->user->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $rec->check_in_at->timezone('Asia/Muscat')->format('H:i') }}</td>
                            <td class="px-4 py-3">{{ $rec->check_out_at?->timezone('Asia/Muscat')->format('H:i') ?? 'ما زال حاضراً' }}</td>
                            <td class="px-4 py-3">{{ $rec->minutes !== null ? intdiv((int) $rec->minutes, 60) . 'س ' . ((int) $rec->minutes) % 60 . 'د' : '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">لم يسجّل أحد حضوره اليوم بعد</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- سجلّي هذا الشهر --}}
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            <p class="px-4 pt-4 pb-2 font-bold text-gold-dark text-sm">سجلّي هذا الشهر</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">اليوم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الحضور</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الانصراف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المدة</th></tr></thead>
                    <tbody>
                        @forelse($attendanceMonth as $rec)
                        <tr class="border-b border-gray-100">
                            <td class="px-4 py-3">{{ $rec->work_date->translatedFormat('D j M') }}</td>
                            <td class="px-4 py-3">{{ $rec->check_in_at->timezone('Asia/Muscat')->format('H:i') }}</td>
                            <td class="px-4 py-3">{{ $rec->check_out_at?->timezone('Asia/Muscat')->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $rec->minutes !== null ? intdiv((int) $rec->minutes, 60) . 'س ' . ((int) $rec->minutes) % 60 . 'د' : '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">لا سجلات هذا الشهر</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if($tab === 'employees' && $isAdmin)
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if(count($chartData) > 0)
            <div class="p-5 border-b border-gray-200">
                <h3 class="text-sm font-bold text-gold-dark mb-4">توزيع التقييمات</h3>
                <div class="flex items-center gap-8">
                    <div class="w-48 h-48"><canvas id="hrRatingChart"></canvas></div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-green-400"></span><span class="text-sm text-gray-700">ممتاز (4-5): <strong class="text-gray-900">{{ $ratingDistribution['excellent'] }}</strong></span></div>
                        <div class="flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-yellow-400"></span><span class="text-sm text-gray-700">جيد (3-4): <strong class="text-gray-900">{{ $ratingDistribution['good'] }}</strong></span></div>
                        <div class="flex items-center gap-3"><span class="w-3 h-3 rounded-full bg-red-400"></span><span class="text-sm text-gray-700">ضعيف (>3): <strong class="text-gray-900">{{ $ratingDistribution['poor'] }}</strong></span></div>
                    </div>
                </div>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الاسم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">البريد</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الدور</th><th class="text-right px-4 py-3 font-bold text-gold-dark">قضايا</th><th class="text-right px-4 py-3 font-bold text-gold-dark">مهام</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التقييم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الحالة</th></tr></thead>
                    <tbody>
                        @foreach($employees as $emp)
                            @php $d = collect($chartData)->firstWhere('name', $emp->name) ?? ['cases'=>0,'tasks'=>0,'tasks_done'=>0,'rating'=>0]; @endphp
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $emp->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $emp->email }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gold/10 text-gold-dark border-gold/25">{{ $emp->role }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $d['cases'] }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $d['tasks_done'] }}/{{ $d['tasks'] }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $d['rating'] >= 4 ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($d['rating'] >= 3 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-gray-100 text-gray-400 border-gray-200') }}">{{ $d['rating'] }}</span></td>
                                <td class="px-4 py-3">@if($emp->is_active)<span class="text-xs text-green-700">نشط</span>@else<span class="text-xs text-red-700">غير نشط</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @elseif($tab === 'performance')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">التقييمات</h2>
                <button @click="$dispatch('open-modal', 'perfModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ تقييم</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التاريخ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التقييم</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المقيم</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($performances as $p)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $p->employee->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->review_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $p->rating >= 4 ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($p->rating >= 3 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 'bg-red-100 text-red-700 border-red-200') }}">{{ $p->rating }}/5</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->reviewer->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.performance.destroy', $p) }}" data-confirm="حذف التقييم؟" class="inline">@csrf @method('DELETE')<button aria-label="{{ __('app.delete') }}" class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                            @if($p->notes)<tr class="border-b border-gray-100"><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 pb-3 text-xs text-gray-400">{{ $p->notes }}</td></tr>@endif
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-4 py-12 text-center text-gray-400">لا توجد تقييمات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $performances->appends(['tab' => 'performance'])->links() }}</div>
        </div>

    @elseif($tab === 'bonuses')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">المكافآت</h2>
                <button @click="$dispatch('open-modal', 'bonusModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ مكافأة</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المبلغ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">السبب</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التاريخ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">بواسطة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($bonuses as $b)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $b->employee->name }}</td>
                                <td class="px-4 py-3 font-bold text-green-700">{{ number_format($b->amount, 2) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $b->reason }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $b->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $b->giver->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.bonuses.destroy', $b) }}" data-confirm="حذف المكافأة؟" class="inline">@csrf @method('DELETE')<button aria-label="{{ __('app.delete') }}" class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">لا توجد مكافآت</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $bonuses->appends(['tab' => 'bonuses'])->links() }}</div>
        </div>

    @elseif($tab === 'penalties')
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            @if($isAdmin)
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">الجزاءات</h2>
                <button @click="$dispatch('open-modal', 'penaltyModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ جزاء</button>
            </div>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">المبلغ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">السبب</th><th class="text-right px-4 py-3 font-bold text-gold-dark">التاريخ</th><th class="text-right px-4 py-3 font-bold text-gold-dark">بواسطة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($penalties as $p)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $p->employee->name }}</td>
                                <td class="px-4 py-3 font-bold {{ $p->amount ? 'text-red-700' : 'text-gray-400' }}">{{ $p->amount ? number_format($p->amount, 2) : '-' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->reason }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $p->giver->name }}</td>
                                @if($isAdmin)
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" action="{{ route('hr.penalties.destroy', $p) }}" data-confirm="حذف الجزاء؟" class="inline">@csrf @method('DELETE')<button aria-label="{{ __('app.delete') }}" class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center mx-auto"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">لا توجد جزاءات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $penalties->appends(['tab' => 'penalties'])->links() }}</div>
        </div>

    @elseif($tab === 'leaves')
        @php $canManageTypes = auth()->user()->isDeveloper() || auth()->user()->role === 'admin' || auth()->user()->hasPermission('salaries.manage'); @endphp

        @if($canManageTypes)
        {{-- أنواع الإجازات: حكمُ كل نوع في الراتب يضبطه المدير هنا.
             لا حذف — نوعٌ يُحذف يُغيّر كشوف الشهور الماضية بأثر رجعي،
             والتعطيل يُخرجه من الاختيار ويُبقي ما بُني عليه سليماً. --}}
        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden mb-4"
             x-data="{ open: false }">
            <button @click="open = !open" type="button"
                    class="w-full p-4 flex items-center justify-between text-start">
                <h2 class="text-sm font-bold text-gold-dark">أنواع الإجازات وأثرها في الراتب</h2>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-collapse class="border-t border-gray-200 p-4 space-y-3">
                @foreach(\App\Models\HrLeaveType::orderBy('sort')->orderBy('id')->get() as $lt)
                    <form method="POST" action="{{ route('leave-types.update', $lt) }}"
                          class="flex flex-wrap items-center gap-3 p-3 rounded-lg bg-gray-50">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $lt->name }}" required maxlength="120"
                               class="flex-1 min-w-[140px] rounded-lg border border-gray-200 px-3 py-2 text-sm">
                        <label class="flex items-center gap-2 text-xs text-gray-600 whitespace-nowrap">
                            <input type="checkbox" name="affects_salary" value="1" @checked($lt->affects_salary)>
                            يخصم من الراتب
                        </label>
                        <label class="flex items-center gap-2 text-xs text-gray-600 whitespace-nowrap">
                            <input type="checkbox" name="is_active" value="1" @checked($lt->is_active)>
                            متاح للاختيار
                        </label>
                        <button class="px-4 py-2 rounded-lg text-xs font-semibold bg-primary text-white">حفظ</button>
                    </form>
                @endforeach

                <form method="POST" action="{{ route('leave-types.store') }}"
                      class="flex flex-wrap items-center gap-3 p-3 rounded-lg border border-dashed border-gray-300">
                    @csrf
                    <input type="text" name="name" required maxlength="120" placeholder="اسم النوع الجديد"
                           class="flex-1 min-w-[140px] rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <input type="text" name="code" required maxlength="40" placeholder="رمز لاتيني (hajj)" dir="ltr"
                           class="w-40 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <label class="flex items-center gap-2 text-xs text-gray-600 whitespace-nowrap">
                        <input type="checkbox" name="affects_salary" value="1">
                        يخصم من الراتب
                    </label>
                    <button class="px-4 py-2 rounded-lg text-xs font-semibold border border-gold-dark text-gold-dark">إضافة</button>
                </form>
            </div>
        </div>
        @endif

        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <h2 class="text-sm font-bold text-gold-dark">الإجازات</h2>
                <button @click="$dispatch('open-modal', 'leaveModal')" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">+ طلب إجازة</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-gray-200"><th class="text-right px-4 py-3 font-bold text-gold-dark">الموظف</th><th class="text-right px-4 py-3 font-bold text-gold-dark">النوع</th><th class="text-right px-4 py-3 font-bold text-gold-dark">من</th><th class="text-right px-4 py-3 font-bold text-gold-dark">إلى</th><th class="text-right px-4 py-3 font-bold text-gold-dark">الحالة</th>@if($isAdmin)<th class="text-center px-4 py-3 font-bold text-gold-dark">إجراءات</th>@endif</tr></thead>
                    <tbody>
                        @forelse($leaves as $l)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-gray-900">{{ $l->employee->name }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-500 border-gray-200">{{ $l->type }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $l->start_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $l->end_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $l->status === 'approved' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($l->status === 'rejected' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-yellow-100 text-yellow-700 border-yellow-200') }}">{{ $l->status === 'approved' ? 'معتمدة' : ($l->status === 'rejected' ? 'مرفوضة' : 'قيد الانتظار') }}</span></td>
                                @if($isAdmin)
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-1">
                                        @if($l->status === 'pending')
                                            <form method="POST" action="{{ route('hr.leaves.approve', $l) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-green-100 text-green-700 hover:bg-green-200 transition-colors flex items-center justify-center" title="موافقة"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></button></form>
                                            <form method="POST" action="{{ route('hr.leaves.reject', $l) }}" class="inline">@csrf<button class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center" title="رفض"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button></form>
                                        @endif
                                        <form method="POST" action="{{ route('hr.leaves.destroy', $l) }}" data-confirm="حذف الإجازة؟" class="inline">@csrf @method('DELETE')<button class="w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors flex items-center justify-center" title="حذف"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @if($l->reason)<tr class="border-b border-gray-100"><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 pb-3 text-xs text-gray-400">{{ $l->reason }}</td></tr>@endif
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-gray-400">لا توجد إجازات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $leaves->appends(['tab' => 'leaves'])->links() }}</div>
        </div>

    @elseif($tab === 'attendance_log')
        {{-- سجلّ الحضور: عدّادات اليوم، ثم حالة الفريق، ثم الجدول.
             الألوان من النظام لا من رقمٍ أكتبه — الوضع الداكن يقلبها
             معه، ولا يبقى اسمٌ أسودَ على أسود. --}}
        @if($isManagerAtt)
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach([
                ['حاضرون الآن', $attStats['present'], 'text-emerald-500'],
                ['انصرفوا', $attStats['completed'], 'text-gray-500'],
                ['في إجازة', $attStats['on_leave'], 'text-gold-dark'],
                ['غائبون', $attStats['absent'], 'text-red-500'],
            ] as [$label, $value, $tone])
                <div class="bg-white rounded-xl border border-gold/15 p-5">
                    <p class="text-xs text-gray-400 mb-1">{{ $label }}</p>
                    <p class="text-2xl font-bold {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-sm font-bold text-gold-dark">حالة الفريق اليوم</h2>
            </div>
            <div class="p-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($attBoard as $row)
                    @php
                        $tone = match ($row['status']) {
                            'present'   => ['حاضر', 'bg-emerald-500', 'text-emerald-600'],
                            'completed' => ['منتهٍ', 'bg-gray-400', 'text-gray-500'],
                            'on_leave'  => ['إجازة', 'bg-gold', 'text-gold-dark'],
                            default     => ['غائب', 'bg-gray-300', 'text-gray-400'],
                        };
                    @endphp
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $tone[1] }}"></span>
                        <span class="text-sm font-medium text-gray-700 truncate flex-1">{{ $row['employee']->name }}</span>
                        <span class="text-[11px] font-semibold flex-shrink-0 {{ $tone[2] }}">{{ $tone[0] }}</span>
                        @if($row['record'])
                            <span class="text-[11px] text-gray-400 flex-shrink-0" dir="ltr">
                                {{ $row['record']->check_in_at->timezone('Asia/Muscat')->format('h:i A') }}@if($row['record']->check_out_at) — {{ $row['record']->check_out_at->timezone('Asia/Muscat')->format('h:i A') }}@endif
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <form method="GET" class="bg-white rounded-xl border border-gold/15 p-4 mb-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="attendance_log">
            <div>
                <label class="block text-xs text-gray-400 mb-1">المدى</label>
                <select name="range" class="rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm">
                    <option value="day" @selected($attRange === 'day')>اليوم</option>
                    <option value="week" @selected($attRange === 'week')>الأسبوع</option>
                    <option value="month" @selected($attRange === 'month')>الشهر</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">التاريخ</label>
                <input type="date" name="date" value="{{ $attDate->toDateString() }}"
                       class="rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm">
            </div>
            @if($isManagerAtt)
            <div>
                <label class="block text-xs text-gray-400 mb-1">الموظف</label>
                <select name="employee_id" class="rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm">
                    <option value="">الجميع</option>
                    @foreach($employees as $e)
                        <option value="{{ $e->id }}" @selected(request('employee_id') == $e->id)>{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs text-gray-400 mb-1">الحالة</label>
                <select name="status" class="rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm">
                    <option value="">الكل</option>
                    <option value="present" @selected(request('status') === 'present')>حاضر</option>
                    <option value="completed" @selected(request('status') === 'completed')>منتهٍ</option>
                </select>
            </div>
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2 rounded-lg font-semibold text-sm transition-colors">تصفية</button>
            <a href="{{ route('hr.index', ['tab' => 'attendance_log']) }}" class="text-gray-400 hover:text-gray-600 text-sm px-3 py-2">مسح</a>
        </form>

        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach(['الموظف','التاريخ','الحضور','الانصراف','المدة','الحالة'] as $h)
                                <th class="text-start px-4 py-3 font-semibold text-xs text-gold-dark">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attRecords as $r)
                            @php $isIn = $r->check_out_at === null; @endphp
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 text-gray-700">{{ $r->user->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $r->work_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-700" dir="ltr">{{ $r->check_in_at->timezone('Asia/Muscat')->format('h:i A') }}</td>
                                <td class="px-4 py-3 text-gray-700" dir="ltr">{{ $r->check_out_at ? $r->check_out_at->timezone('Asia/Muscat')->format('h:i A') : '—' }}</td>
                                <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $r->minutes === null ? '—' : intdiv($r->minutes, 60) . 'س ' . ($r->minutes % 60) . 'د' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $isIn ? 'bg-emerald-500/10 text-emerald-600' : 'bg-gray-500/10 text-gray-500' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $isIn ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                        {{ $isIn ? 'حاضر' : 'منتهٍ' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">لا سجلات في هذا المدى.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200">{{ $attRecords->links() }}</div>
        </div>

    @elseif($tab === 'salaries' && $canManageSalaries)
        <div class="bg-white rounded-xl border border-gold/15 p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-gray-400">
                هذه الأرقام لإدارة المكتب وحدها — لا يراها الموظف ولا تصله.
            </p>
            <form method="GET" class="flex items-end gap-2">
                <input type="hidden" name="tab" value="salaries">
                <input type="month" name="period" value="{{ $payPeriod }}"
                       class="rounded-lg bg-white border border-gray-200 px-3 py-2 text-gray-900 text-sm">
                <button class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg font-semibold text-sm transition-colors">عرض</button>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach([
                ['الإجمالي قبل الخصم', number_format($payTotals['gross'], 2) . ' ر.ع', 'text-gray-700'],
                ['الخصومات', number_format($payTotals['deductions'], 2) . ' ر.ع', 'text-red-500'],
                ['الصافي', number_format($payTotals['net'], 2) . ' ر.ع', 'text-emerald-500'],
                ['بلا راتب مُسجَّل', $payTotals['without_salary'], 'text-gold-dark'],
            ] as [$label, $value, $tone])
                <div class="bg-white rounded-xl border border-gold/15 p-5">
                    <p class="text-xs text-gray-400 mb-1">{{ $label }}</p>
                    <p class="text-xl font-bold {{ $tone }}" dir="ltr">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gold/15 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            @foreach(['الموظف','الأساسي','البدلات','أيام إجازة خاصمة','خصم الإجازة','خصومات أخرى','الصافي',''] as $h)
                                <th class="text-start px-4 py-3 font-semibold text-xs text-gold-dark">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payslips as $p)
                            <tr class="border-t border-gray-200">
                                <td class="px-4 py-3 font-medium text-gray-700">{{ $p['employee']->name }}</td>
                                <td class="px-4 py-3 text-gray-700" dir="ltr">{{ $p['has_salary'] ? number_format($p['basic'], 2) : '—' }}</td>
                                <td class="px-4 py-3 text-gray-700" dir="ltr">{{ number_format($p['allowances'], 2) }}</td>
                                <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $p['unpaid_days'] ?: '—' }}</td>
                                <td class="px-4 py-3 {{ $p['leave_deduction'] > 0 ? 'text-red-500' : 'text-gray-500' }}" dir="ltr">
                                    {{ $p['leave_deduction'] > 0 ? number_format($p['leave_deduction'], 2) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500" dir="ltr">{{ $p['other_deductions'] > 0 ? number_format($p['other_deductions'], 2) : '—' }}</td>
                                <td class="px-4 py-3 font-bold text-gray-700" dir="ltr">{{ $p['has_salary'] ? number_format($p['net'], 2) : '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('salaries.show', $p['employee']) }}?period={{ $payPeriod }}"
                                       class="text-xs font-semibold text-gold-dark hover:underline">الكشف</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gold/15 p-6">
                <h2 class="text-sm font-bold text-gold-dark mb-4">تحديد راتب موظف</h2>
                <form method="POST" action="{{ route('salaries.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5">الموظف</label>
                        <select name="employee_id" required class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                            @foreach($employees as $e)
                                <option value="{{ $e->id }}">{{ $e->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1.5">الراتب الأساسي (ر.ع)</label>
                            <input type="number" step="0.01" min="0" name="basic_salary" required dir="ltr"
                                   class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1.5">البدلات الثابتة</label>
                            <input type="number" step="0.01" min="0" name="allowances" value="0" dir="ltr"
                                   class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5">ملاحظة</label>
                        <input type="text" name="note" maxlength="255"
                               class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
                    </div>
                    <button class="w-full bg-primary hover:bg-primary-dark text-white py-2.5 rounded-lg font-semibold text-sm transition-colors">حفظ الراتب</button>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-gold/15 p-6">
                <h2 class="text-sm font-bold text-gold-dark mb-4">طريقة حساب اليوم</h2>
                <form method="POST" action="{{ route('salaries.settings') }}" class="space-y-3">
                    @csrf
                    <p class="text-xs text-gray-400 mb-2 leading-relaxed">
                        قيمة اليوم = الراتب الأساسي ÷ عدد أيام الشهر. اختر ما يوافق سياسة مكتبك.
                    </p>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" name="hr_month_days_mode" value="fixed30" @checked($monthDaysMode === 'fixed30')>
                        <span class="text-sm text-gray-700">شهر ثابت — ٣٠ يومًا دائمًا</span>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" name="hr_month_days_mode" value="actual" @checked($monthDaysMode === 'actual')>
                        <span class="text-sm text-gray-700">أيام الشهر الفعلية (٢٨–٣١)</span>
                    </label>
                    <button class="w-full border border-gold-dark text-gold-dark py-2.5 rounded-lg font-semibold text-sm hover:bg-gold/5 transition-colors">حفظ الطريقة</button>
                    <p class="text-[11px] text-gray-400 leading-relaxed pt-2">
                        هذه الأرقام لإدارة المكتب داخليًا، ولا تُغني عن المتطلبات المحاسبية أو القانونية الرسمية.
                    </p>
                </form>
            </div>
        </div>
    @endif
</div>

{{-- Performance Modal --}}
@if($isAdmin)
<div id="perfModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'perfModal') open = true" @keydown.escape="open = false">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">إضافة تقييم</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.performance.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التاريخ <span class="text-red-700">*</span></label>
                    <input type="date" name="review_date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التقييم <span class="text-red-700">*</span></label>
                    <select name="rating" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }} نجوم</option>@endfor</select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">ملاحظات</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Bonus Modal --}}
<div id="bonusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'bonusModal') open = true" @keydown.escape="open = false">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">إضافة مكافأة</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.bonuses.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">المبلغ <span class="text-red-700">*</span></label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التاريخ <span class="text-red-700">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">السبب <span class="text-red-700">*</span></label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Penalty Modal --}}
<div id="penaltyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'penaltyModal') open = true" @keydown.escape="open = false">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">إضافة جزاء</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.penalties.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                    <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">المبلغ</label>
                    <input type="number" step="0.001" name="amount" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">التاريخ <span class="text-red-700">*</span></label>
                    <input type="date" name="date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">السبب <span class="text-red-700">*</span></label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">حفظ</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Leave Modal --}}
<div id="leaveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ open: false }" x-show="open" x-cloak x-on:open-modal.window="if ($event.detail === 'leaveModal') open = true" @keydown.escape="open = false">
    <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="open = false"></div>
    <div class="relative bg-white border border-gold/25 rounded-2xl shadow-2xl w-full max-w-lg" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gold-dark">طلب إجازة</h3>
            <button @click="open = false" aria-label="{{ __('app.close') }}" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-900"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form method="POST" action="{{ route('hr.leaves.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            @if(auth()->user()->isAdmin() || auth()->user()->isDeveloper())
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف <span class="text-red-700">*</span></label>
                <select name="employee_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required><option value="">اختر</option>@foreach($employees as $emp)<option value="{{ $emp->id }}">{{ $emp->name }}</option>@endforeach</select>
            </div>
            @else
            <input type="hidden" name="employee_id" value="{{ auth()->id() }}">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">الموظف</label>
                <input type="text" value="{{ auth()->user()->name }}" disabled class="w-full rounded-lg bg-gray-100 border border-gray-200 px-4 py-2.5 text-gray-900 text-sm">
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">النوع <span class="text-red-700">*</span></label>
                <select name="leave_type_id" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                    <option value="">اختر</option>
                    {{-- الأنواع من الجدول: مكتبٌ أضاف نوعاً يجده هنا بلا لمس كود --}}
                    @foreach(\App\Models\HrLeaveType::selectable() as $lt)
                        <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">من <span class="text-red-700">*</span></label>
                    <input type="date" name="start_date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1.5">إلى <span class="text-red-700">*</span></label>
                    <input type="date" name="end_date" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">السبب</label>
                <textarea name="reason" rows="2" class="w-full rounded-lg bg-white border border-gray-200 px-4 py-2.5 text-gray-900 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">إرسال الطلب</button>
                <button type="button" @click="open = false" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-lg font-medium transition-colors text-sm">إلغاء</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@if($tab === 'employees' && $isAdmin && count($chartData) > 0)
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('hrRatingChart'), {
        type: 'doughnut',
        data: {
            labels: ['ممتاز (4-5)', 'جيد (3-4)', 'ضعيف (>3)'],
            datasets: [{
                data: [{{ $ratingDistribution['excellent'] }}, {{ $ratingDistribution['good'] }}, {{ $ratingDistribution['poor'] }}],
                // تقدير لا فئة: ألوان الحالة، وتتبع الوضع الفاتح والداكن
                backgroundColor: [MdChart.status('good'), MdChart.status('warn'), MdChart.status('bad')],
                borderColor: MdChart.surface(),
                borderWidth: 2,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',
            plugins: {
                // المفتاح ظاهر: التقدير لا يُعرَف باللون وحده
                legend: MdChart.legend(),
                tooltip: Object.assign(MdChart.tooltip(), {
                    callbacks: {
                        label: function (ctx) { return ctx.parsed + ' {{ __("app.employee") ?? "موظف" }}'; }
                    }
                })
            }
        }
    });
});
</script>
@endif
@endpush
