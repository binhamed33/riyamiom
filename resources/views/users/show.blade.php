@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ $user->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('users.edit', $user) }}" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.edit') }}</a>
            <a href="{{ route('users.index') }}" class="text-white/50 hover:text-white/70 transition-colors text-sm flex items-center gap-1">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-navy-light rounded-xl border border-ivory/10 p-6">
                <h2 class="text-lg font-semibold text-gold mb-4">{{ __('app.user_name') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.name') }}</p>
                        <p class="text-ivory font-medium">{{ $user->name }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.email') }}</p>
                        <p class="text-ivory font-medium">{{ $user->email }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.user_role') }}</p>
                        @php
                            $roleLabels = ['developer' => __('app.developer'), 'admin' => __('app.admin'), 'lawyer' => __('app.lawyer'), 'staff' => __('app.staff'), 'client' => __('app.client_role')];
                            $roleColors = [
                                'developer' => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                'admin' => 'bg-red-500/20 text-red-400 border-red-500/30',
                                'lawyer' => 'bg-gold/20 text-gold border-gold/30',
                                'staff' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                'client' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $roleColors[$user->role] ?? 'bg-ivory/10 text-ivory/50 border-ivory/10' }}">
                            {{ $roleLabels[$user->role] ?? $user->role }}
                        </span>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.user_status') }}</p>
                        @if($user->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">{{ __('app.active') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-ivory/10 text-ivory/50 border border-ivory/10">{{ __('app.inactive') }}</span>
                        @endif
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.phone') }}</p>
                        <p class="text-ivory font-medium" dir="ltr">{{ $user->phone ?? '—' }}</p>
                    </div>
                    <div class="bg-navy rounded-lg p-4">
                        <p class="text-ivory/40 text-sm mb-1">{{ __('app.created_at') }}</p>
                        <p class="text-ivory font-medium">{{ $user->created_at?->format('Y-m-d') ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if($user->role === 'lawyer')
            <div class="space-y-6">
                <div class="bg-navy-light rounded-xl border border-gold/20 p-6">
                    <h3 class="text-sm font-semibold text-gold mb-4">{{ __('app.lawyer_stats') }}</h3>
                    <div class="space-y-4">
                        <div class="bg-navy rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-gold">{{ $user->cases_count ?? 0 }}</p>
                            <p class="text-ivory/50 text-sm mt-1">{{ __('app.active_cases_count') }}</p>
                        </div>
                        <div class="bg-navy rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-gold">{{ $user->tasks_count ?? 0 }}</p>
                            <p class="text-ivory/50 text-sm mt-1">{{ __('app.assigned_tasks') }}</p>
                        </div>
                        <div class="bg-navy rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-gold">{{ $user->efficiency ?? 0 }}%</p>
                            <p class="text-ivory/50 text-sm mt-1">{{ __('app.efficiency') }}</p>
                            @php
                                $efficiency = $user->efficiency ?? 0;
                                $barColor = $efficiency > 80 ? 'bg-emerald-500' : ($efficiency >= 60 ? 'bg-amber-500' : 'bg-red-500');
                            @endphp
                            <div class="w-full bg-navy-lighter rounded-full h-2 mt-2">
                                <div class="{{ $barColor }} h-2 rounded-full transition-all" style="width: {{ $efficiency }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
