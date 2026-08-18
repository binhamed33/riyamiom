@extends('layouts.app')

@section('title', 'طلبات التسجيل')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gold-dark">طلبات التسجيل</h1>
            <p class="text-sm text-gray-500 mt-1">طلبات الاشتراك القادمة من صفحة التسجيل في الموقع التعريفي.</p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-gold/12 border border-gold/25 px-4 py-1.5 text-sm font-bold text-gold-dark">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            {{ $requests->total() }} طلب
        </span>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    @if($requests->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="mt-4 text-sm text-gray-500">لا توجد طلبات بعد — ستظهر هنا طلبات الاشتراك من صفحة التسجيل.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-right">
                            <th class="px-5 py-3.5 font-bold text-gray-600">المكتب</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">المسؤول</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">التواصل</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">الحجم</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">الملاحظات</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">التاريخ</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">الحالة</th>
                            <th class="px-5 py-3.5 font-bold text-gray-600">تحديث الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($requests as $r)
                            <tr class="hover:bg-gray-50/60 transition-colors align-top">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-gray-800">{{ $r->office_name }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $r->city ?: '—' }}</p>
                                </td>
                                <td class="px-5 py-4 text-gray-600">{{ $r->contact_name }}</td>
                                <td class="px-5 py-4">
                                    <p class="font-latin text-gray-600" dir="ltr">{{ $r->phone }}</p>
                                    <p class="font-latin text-xs text-gray-400 mt-0.5" dir="ltr">{{ $r->email }}</p>
                                </td>
                                <td class="px-5 py-4 text-gray-600">{{ $r->lawyers_count_label }}</td>
                                <td class="px-5 py-4 max-w-[220px]">
                                    <p class="text-gray-500 whitespace-pre-wrap leading-relaxed text-xs">{{ $r->notes ?: '—' }}</p>
                                </td>
                                <td class="px-5 py-4 text-gray-500 whitespace-nowrap text-xs">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold {{ \App\Models\RegistrationRequest::STATUS_COLORS[$r->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
                                        {{ $r->status_label }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <form method="POST" action="{{ route('marketing.requests.status', $r) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="status" class="bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:border-gold/40">
                                            @foreach (\App\Models\RegistrationRequest::STATUSES as $key => $label)
                                                <option value="{{ $key }}" @selected($r->status === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="text-gold-dark hover:bg-gold/12 rounded-lg p-1.5 transition-colors" title="حفظ">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5">
            {{ $requests->links() }}
        </div>
    @endif
</div>
@endsection
