@extends('layouts.app')

@section('title', __('app.page_audit_log'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold-dark">{{ __('app.page_audit_log') }}</h1>
    </div>

    <form method="GET" action="{{ route('audit-log.index') }}">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">{{ __('app.audit_user') }}</label>
                    <select name="user_id" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                        <option value="">{{ __('app.all_users') }}</option>
                        @foreach($users ?? [] as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">{{ __('app.audit_action') }}</label>
                    <select name="action" class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40">
                        <option value="">{{ __('app.all_actions') }}</option>
                        <option value="create" {{ request('action') === 'create' ? 'selected' : '' }}>{{ __('app.create') }}</option>
                        <option value="update" {{ request('action') === 'update' ? 'selected' : '' }}>{{ __('app.edit') }}</option>
                        <option value="delete" {{ request('action') === 'delete' ? 'selected' : '' }}>{{ __('app.delete') }}</option>
                        <option value="login" {{ request('action') === 'login' ? 'selected' : '' }}>{{ __('app.login') }}</option>
                        <option value="logout" {{ request('action') === 'logout' ? 'selected' : '' }}>{{ __('app.logout') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">{{ __('app.model_name') }}</label>
                    <input
                        type="text"
                        name="model"
                        value="{{ request('model') }}"
                        placeholder="{{ __('app.model_name') }}"
                        class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                    >
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">{{ __('app.from_date_label') }}</label>
                        <input
                            type="date"
                            name="date_from"
                            value="{{ request('date_from') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                        >
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">{{ __('app.to_date_label') }}</label>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ request('date_to') }}"
                            class="w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm focus:ring-2 focus:ring-gold-dark focus:border-gold/40"
                        >
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-4">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.apply_filter') }}</button>
                <a href="{{ route('audit-log.index') }}" class="text-gray-500 hover:text-gray-900 transition text-sm">{{ __('app.reset_filter') }}</a>
            </div>
        </div>
    </form>

    @php
        $__sortOptions = ['created' => __('app.sort_newest'), 'action' => __('app.action'), 'user' => __('app.user')];
        $__sortDefault = 'created';
    @endphp

    {{-- §4: الترتيب --}}
    <div class="flex items-center gap-3 flex-wrap">
        <x-sort-bar :options="$__sortOptions" :default="$__sortDefault" :default-dir="$__sortDefaultDir ?? 'desc'" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.table_datetime') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.audit_user') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.table_action') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.model_name') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.table_details') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold-dark">{{ __('app.table_ip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs ?? [] as $log)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-gray-500 text-sm whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                            <td class="px-6 py-4 text-gray-700 font-medium">{{ $log->user->name ?? __('app.system') }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $actionStyles = [
                                        'create' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                        'update' => 'bg-blue-100 text-blue-700 border-blue-200',
                                        'delete' => 'bg-red-100 text-red-700 border-red-200',
                                        'login' => 'bg-gold/12 text-gold-dark border-gold/15',
                                        'logout' => 'bg-gray-100 text-gray-500 border-gray-200',
                                    ];
                                    $actionLabels = [
                                        'create' => __('app.create'),
                                        'update' => __('app.edit'),
                                        'delete' => __('app.delete'),
                                        'login' => __('app.login'),
                                        'logout' => __('app.logout'),
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $actionStyles[$log->action] ?? 'bg-gray-100 text-gray-500 border-gray-200' }}">
                                    {{ $actionLabels[$log->action] ?? $log->action }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-700 text-sm">{{ class_basename($log->model_type ?? '') ?: '—' }}</td>
                            <td class="px-6 py-4 text-gray-500 text-sm max-w-xs truncate">
                                @if($log->new_values)
                                    @php
                                        $details = '';
                                        try {
                                            $details = collect($log->new_values)->map(function ($v, $k) {
                                                return '<span class="text-gray-400">' . e($k) . ':</span> ' . (is_array($v) ? e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) : e($v));
                                            })->implode(', ');
                                        } catch (\Throwable $e) {
                                            $details = '— (decode error)';
                                        }
                                    @endphp
                                    {!! $details !!}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-400 text-sm" dir="ltr">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">{{ __('app.no_logs') }}</td>
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
