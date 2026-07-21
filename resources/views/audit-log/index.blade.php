@extends('layouts.app')

@section('title', __('app.page_audit_log'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ __('app.page_audit_log') }}</h1>
    </div>

    <form method="GET" action="{{ route('audit-log.index') }}">
        <div class="bg-navy-light rounded-xl border border-ivory/10 p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs text-ivory/40 mb-1">{{ __('app.audit_user') }}</label>
                    <select name="user_id" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                        <option value="">{{ __('app.all_users') }}</option>
                        @foreach($users ?? [] as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-ivory/40 mb-1">{{ __('app.audit_action') }}</label>
                    <select name="action" class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                        <option value="">{{ __('app.all_actions') }}</option>
                        <option value="create" {{ request('action') === 'create' ? 'selected' : '' }}>{{ __('app.create') }}</option>
                        <option value="update" {{ request('action') === 'update' ? 'selected' : '' }}>{{ __('app.edit') }}</option>
                        <option value="delete" {{ request('action') === 'delete' ? 'selected' : '' }}>{{ __('app.delete') }}</option>
                        <option value="login" {{ request('action') === 'login' ? 'selected' : '' }}>{{ __('app.login') }}</option>
                        <option value="logout" {{ request('action') === 'logout' ? 'selected' : '' }}>{{ __('app.logout') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-ivory/40 mb-1">{{ __('app.model_name') }}</label>
                    <input
                        type="text"
                        name="model"
                        value="{{ request('model') }}"
                        placeholder="{{ __('app.model_name') }}"
                        class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                    >
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-ivory/40 mb-1">{{ __('app.from_date_label') }}</label>
                        <input
                            type="date"
                            name="date_from"
                            value="{{ request('date_from') }}"
                            class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                        >
                    </div>
                    <div>
                        <label class="block text-xs text-ivory/40 mb-1">{{ __('app.to_date_label') }}</label>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ request('date_to') }}"
                            class="w-full rounded-lg bg-[#0D1321] border border-white/20 text-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
                        >
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-4">
                <button type="submit" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.apply_filter') }}</button>
                <a href="{{ route('audit-log.index') }}" class="text-ivory/50 hover:text-ivory transition text-sm">{{ __('app.reset_filter') }}</a>
            </div>
        </div>
    </form>

    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-ivory/10">
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.table_datetime') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.audit_user') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.table_action') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.model_name') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.table_details') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.table_ip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ivory/5">
                    @forelse($logs ?? [] as $log)
                        <tr class="hover:bg-navy-lighter/50 transition">
                            <td class="px-6 py-4 text-ivory/50 text-sm whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td class="px-6 py-4 text-ivory font-medium">{{ $log->user->name ?? __('app.system') }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $actionStyles = [
                                        'create' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                                        'update' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                        'delete' => 'bg-red-500/20 text-red-400 border-red-500/30',
                                        'login' => 'bg-gold/20 text-gold border-gold/30',
                                        'logout' => 'bg-ivory/10 text-ivory/50 border-ivory/10',
                                    ];
                                    $actionLabels = [
                                        'create' => __('app.create'),
                                        'update' => __('app.edit'),
                                        'delete' => __('app.delete'),
                                        'login' => __('app.login'),
                                        'logout' => __('app.logout'),
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $actionStyles[$log->action] ?? 'bg-ivory/10 text-ivory/50 border-ivory/10' }}">
                                    {{ $actionLabels[$log->action] ?? $log->action }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-ivory/70 text-sm">{{ class_basename($log->model_type ?? '') ?: '—' }}</td>
                            <td class="px-6 py-4 text-ivory/50 text-sm max-w-xs truncate">
                                @if($log->new_values)
                                    @foreach($log->new_values as $k => $v)
                                        <span class="text-ivory/40">{{ $k }}:</span> {{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}@if(!$loop->last), @endif
                                    @endforeach
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 text-ivory/40 text-sm" dir="ltr">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-ivory/30">{{ __('app.no_logs') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(isset($logs) && method_exists($logs, 'links'))
        <div class="mt-4">
            {{ $logs->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection