@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-amber-600">{{ $user->name }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('users.edit', $user) }}" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">{{ __('app.edit') }}</a>
            <a href="{{ route('users.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors text-sm flex items-center gap-1">{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-amber-600 mb-4">{{ __('app.user_name') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.name') }}</p>
                        <p class="text-gray-700 font-medium">{{ $user->name }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.email') }}</p>
                        <p class="text-gray-700 font-medium">{{ $user->email }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.user_role') }}</p>
                        @php
                            $roleLabels = ['developer' => __('app.developer'), 'admin' => __('app.admin'), 'lawyer' => __('app.lawyer'), 'staff' => __('app.staff'), 'client' => __('app.client_role')];
                            $roleColors = [
                                'developer' => 'bg-purple-100 text-purple-700 border-purple-200',
                                'admin' => 'bg-red-100 text-red-700 border-red-200',
                                'lawyer' => 'bg-amber-100 text-amber-600 border-amber-300',
                                'staff' => 'bg-blue-100 text-blue-700 border-blue-200',
                                'client' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            ];
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-500 border-gray-200' }}">
                            {{ $roleLabels[$user->role] ?? $user->role }}
                        </span>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.user_status') }}</p>
                        @if($user->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 border border-emerald-200">{{ __('app.active') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">{{ __('app.inactive') }}</span>
                        @endif
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.phone') }}</p>
                        <p class="text-gray-700 font-medium" dir="ltr">{{ $user->phone ?? '—' }}</p>
                    </div>
                    <div class="bg-white rounded-lg p-4">
                        <p class="text-gray-400 text-sm mb-1">{{ __('app.created_at') }}</p>
                        <p class="text-gray-700 font-medium">{{ $user->created_at?->format('Y-m-d') ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if($user->role === 'lawyer')
            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-amber-200 p-6">
                    <h3 class="text-sm font-semibold text-amber-600 mb-4">{{ __('app.lawyer_stats') }}</h3>
                    <div class="space-y-4">
                        <div class="bg-white rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-amber-600">{{ $user->cases_count ?? 0 }}</p>
                            <p class="text-gray-500 text-sm mt-1">{{ __('app.active_cases_count') }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-amber-600">{{ $user->tasks_count ?? 0 }}</p>
                            <p class="text-gray-500 text-sm mt-1">{{ __('app.assigned_tasks') }}</p>
                        </div>
                        <div class="bg-white rounded-lg p-4 text-center">
                            <p class="text-3xl font-bold text-amber-600">{{ $user->efficiency ?? 0 }}%</p>
                            <p class="text-gray-500 text-sm mt-1">{{ __('app.efficiency') }}</p>
                            @php
                                $efficiency = $user->efficiency ?? 0;
                                $barColor = $efficiency > 80 ? 'bg-emerald-500' : ($efficiency >= 60 ? 'bg-amber-500' : 'bg-red-500');
                            @endphp
                            <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
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
