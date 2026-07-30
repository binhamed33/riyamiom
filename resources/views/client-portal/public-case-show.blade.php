<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $case->title }} - مكتب الرياضي للمحاماة</title>
    <script nonce="{{ $cspNonce ?? '' }}" src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="max-w-5xl mx-auto p-4 md:p-6">
        {{-- Header --}}
        <div class="bg-gradient-to-l from-amber-600 to-amber-700 rounded-2xl shadow-xl p-6 md:p-8 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('client.access.lookup') }}" class="text-white/70 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">{{ $case->title }}</h1>
                        <p class="text-amber-200 text-sm">رقم القضية: #{{ $case->office_case_number ?? $case->case_number }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('client.access.logout') }}">
                    @csrf
                    <button type="submit" class="bg-white/20 text-white px-3 py-1.5 rounded-xl text-sm hover:bg-white/30 transition">
                        خروج
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Main Info --}}
            <div class="md:col-span-2 space-y-6">

                {{-- Case Details --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-4">تفاصيل القضية</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-400">رقم القضية</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->case_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">رقم المكتب</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->office_case_number ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">نوع القضية</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->case_type ?? $case->type }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">المحكمة</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->court }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">الحالة</p>
                            <span class="text-xs px-2 py-0.5 rounded-lg font-medium inline-block mt-1
                                {{ $case->status === 'active' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $case->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                {{ $case->status === 'closed' ? 'bg-gray-100 text-gray-500' : '' }}
                                {{ $case->status === 'won' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ $case->status === 'lost' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $case->status === 'overdue' ? 'bg-orange-100 text-orange-700' : '' }}">
                                @lang('app.status_' . $case->status)
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">الأولوية</p>
                            <span class="text-xs px-2 py-0.5 rounded-lg font-medium inline-block mt-1
                                {{ $case->priority === 'urgent' ? 'bg-red-100 text-red-700' : '' }}
                                {{ $case->priority === 'high' ? 'bg-orange-100 text-orange-700' : '' }}
                                {{ $case->priority === 'medium' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $case->priority === 'low' ? 'bg-gray-100 text-gray-500' : '' }}">
                                @lang('app.priority_' . $case->priority)
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">الخصم</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->opponent ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">هاتف الخصم</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->opponent_phone ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">محامي الخصم</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->opponent_lawyer ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">المحامي المسؤول</p>
                            <p class="text-sm font-medium text-gray-700">{{ $case->lawyer?->name ?? '—' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                @if($case->description)
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider mb-3">الوصف</h2>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $case->description }}</p>
                </div>
                @endif

                {{-- Court Sessions --}}
                @if($case->sessions->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider">الجلسات</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-3 text-right text-gray-400 text-xs">التاريخ</th>
                                    <th class="px-4 py-3 text-right text-gray-400 text-xs">المكان</th>
                                    <th class="px-4 py-3 text-right text-gray-400 text-xs">الحالة</th>
                                    <th class="px-4 py-3 text-right text-gray-400 text-xs">ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($case->sessions as $session)
                                    <tr class="border-t border-gray-100">
                                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $session->date?->format('Y-m-d') }}</td>
                                        <td class="px-4 py-3 text-gray-500">{{ $session->location ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <span class="text-xs px-2 py-0.5 rounded-lg font-medium
                                                {{ $session->status === 'held' ? 'bg-green-100 text-green-700' : '' }}
                                                {{ $session->status === 'postponed' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                                {{ $session->status === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                                                {{ $session->status === 'scheduled' ? 'bg-blue-100 text-blue-700' : '' }}">
                                                @lang('app.status_' . $session->status)
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $session->notes ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar - Documents --}}
            <div class="space-y-6">
                @if($case->documents->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-bold text-amber-600 uppercase tracking-wider">المستندات</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($case->documents as $doc)
                            <div class="px-5 py-3">
                                <p class="text-sm font-medium text-gray-700">{{ $doc->title }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-gray-400">{{ $doc->type }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span class="text-xs text-gray-400">{{ $doc->created_at?->format('Y-m-d') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Footer --}}
        <div class="text-center mt-8">
            <p class="text-xs text-gray-400">مكتب الرياضي للمحاماة &copy; {{ date('Y') }}</p>
        </div>
    </div>

</body>
</html>
