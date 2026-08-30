@extends('layouts.app')

@section('title', 'سجل تنفيذ الأتمتة')

@section('content')
<div class="space-y-6" dir="rtl">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gold-dark">📜 سجل تنفيذ الأتمتة</h1>
            <p class="text-sm text-gray-500 mt-1">كل عملية تنفيذ مسجلة: القاعدة، الموضوع، النتيجة، والخطأ إن وجد — لا فشل صامت</p>
        </div>
        <a href="{{ route('automations.index') }}" class="bg-white hover:bg-gray-100 text-gray-600 border border-gold/15 px-4 py-2.5 rounded-lg font-medium transition text-sm">← مركز الأتمتة</a>
    </div>

    <form method="GET" action="{{ route('automations.runs') }}" class="bg-white rounded-xl border border-gold/15 p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-bold text-gray-600 mb-1">القاعدة</label>
            <select name="automation_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">الكل</option>
                @foreach($automations as $a)
                    <option value="{{ $a->id }}" {{ request('automation_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
            <label class="block text-xs font-bold text-gray-600 mb-1">النتيجة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">الكل</option>
                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>نجح</option>
                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>فشل</option>
                <option value="skipped" {{ request('status') === 'skipped' ? 'selected' : '' }}>تخطّي</option>
            </select>
        </div>
        <button class="bg-primary hover:bg-primary-dark text-white px-5 py-2 rounded-lg font-semibold text-sm transition">تصفية</button>
    </form>

    <div class="bg-white rounded-xl border border-gold/15 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">القاعدة</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">القضية</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">النتيجة</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">التفاصيل</th>
                        <th class="px-4 py-3 text-gold-dark font-bold text-xs">الوقت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-xs font-bold text-gray-900">
                                {{ $run->automation->name ?? ($run->trigger === 'reminder' ? '⏰ تذكير قالب' : 'قاعدة محذوفة') }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($run->case)
                                    <a href="{{ route('cases.show', $run->case_id) }}" class="text-gold-dark hover:underline">{{ Str::limit($run->case->title, 30) }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full
                                    {{ $run->status === 'success' ? 'bg-green-100 text-green-700' : ($run->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500') }}">
                                    {{ $run->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 max-w-[320px]">
                                <span class="block truncate" title="{{ $run->summary }}">{{ $run->summary ?? '—' }}</span>
                                @if($run->error)
                                    <span class="block text-red-600 truncate mt-0.5" title="{{ $run->error }}">⚠ {{ Str::limit($run->error, 80) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ $run->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">لا توجد سجلات تنفيذ بعد</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100">{{ $runs->links() }}</div>
    </div>
</div>
@endsection
