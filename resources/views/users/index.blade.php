@extends('layouts.app')

@section('title', __('app.page_users'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ __('app.page_users') }}</h1>
        <a href="{{ route('users.create') }}" class="bg-gold hover:bg-gold-dark text-navy px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
            + {{ __('app.new_user') }}
        </a>
    </div>

    <form method="GET" action="{{ route('users.index') }}">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="{{ __('app.search_users_placeholder') }}"
                class="flex-1 rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]"
            >
            <select name="role" class="sm:w-auto rounded-lg bg-[#0D1321] border border-white/20 text-white px-4 py-2.5 focus:ring-2 focus:ring-[#C9A55A] focus:border-[#C9A55A]">
                <option value="">{{ __('app.all_roles') }}</option>
                <option value="developer" {{ request('role') === 'developer' ? 'selected' : '' }}>{{ __('app.developer') }}</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>{{ __('app.admin') }}</option>
                <option value="lawyer" {{ request('role') === 'lawyer' ? 'selected' : '' }}>{{ __('app.lawyer') }}</option>
                <option value="staff" {{ request('role') === 'staff' ? 'selected' : '' }}>{{ __('app.staff') }}</option>
                <option value="client" {{ request('role') === 'client' ? 'selected' : '' }}>{{ __('app.client_role') }}</option>
            </select>
            <button type="submit" class="bg-white/5 border border-gold/30 text-gold px-5 py-2 rounded-lg hover:bg-gold/10 transition text-sm">{{ __('app.search') }}</button>
        </div>
    </form>

    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-ivory/10">
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.name') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.email') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.user_role') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.phone') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.user_status') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-gold">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ivory/5">
                    @forelse($users as $user)
                        <tr class="hover:bg-navy-lighter/50 transition">
                            <td class="px-6 py-4">
                                <a href="{{ route('users.show', $user) }}" class="text-ivory hover:text-gold transition font-medium">
                                    {{ $user->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-ivory/70">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $roleColors = [
                                        'developer' => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                        'admin' => 'bg-red-500/20 text-red-400 border-red-500/30',
                                        'lawyer' => 'bg-gold/20 text-gold border-gold/30',
                                        'staff' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                        'client' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                                    ];
                                    $roleLabels = [
                                        'developer' => __('app.developer'),
                                        'admin' => __('app.admin'),
                                        'lawyer' => __('app.lawyer'),
                                        'staff' => __('app.staff'),
                                        'client' => __('app.client_role'),
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $roleColors[$user->role] ?? 'bg-ivory/10 text-ivory/50 border-ivory/10' }}">
                                    {{ $roleLabels[$user->role] ?? $user->role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-ivory/70" dir="ltr">{{ $user->phone ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($user->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">{{ __('app.active') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-ivory/10 text-ivory/50 border border-ivory/10">{{ __('app.inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#C9A55A]/10 text-[#C9A55A] hover:bg-[#C9A55A]/20 transition-colors" title="{{ __('app.edit') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete") }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-colors" title="{{ __('app.delete') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-ivory/30">{{ __('app.no_users') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($users, 'links'))
        <div class="mt-4">
            {{ $users->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
