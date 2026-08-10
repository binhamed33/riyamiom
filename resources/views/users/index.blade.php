@extends('layouts.app')

@section('title', __('app.page_users'))

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-amber-600">{{ __('app.page_users') }}</h1>
        <a href="{{ route('users.create') }}" class="bg-primary hover:bg-primary-dark text-white px-6 py-2.5 rounded-lg font-semibold transition-colors text-sm">
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
                class="flex-1 rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
            >
            <select name="role" class="sm:w-auto rounded-lg bg-white border border-gray-200 text-gray-900 px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                <option value="">{{ __('app.all_roles') }}</option>
                <option value="developer" {{ request('role') === 'developer' ? 'selected' : '' }}>{{ __('app.developer') }}</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>{{ __('app.admin') }}</option>
                <option value="lawyer" {{ request('role') === 'lawyer' ? 'selected' : '' }}>{{ __('app.lawyer') }}</option>
                <option value="staff" {{ request('role') === 'staff' ? 'selected' : '' }}>{{ __('app.staff') }}</option>
                <option value="client" {{ request('role') === 'client' ? 'selected' : '' }}>{{ __('app.client_role') }}</option>
            </select>
            <button type="submit" class="bg-gray-100 border border-amber-300 text-amber-600 px-5 py-2 rounded-lg hover:bg-amber-100 transition text-sm">{{ __('app.search') }}</button>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-right px-6 py-4 text-sm font-semibold text-amber-600">{{ __('app.name') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-amber-600">{{ __('app.email') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-amber-600">{{ __('app.user_role') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-amber-600">{{ __('app.phone') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-amber-600">{{ __('app.user_status') }}</th>
                        <th class="text-right px-6 py-4 text-sm font-semibold text-amber-600">{{ __('app.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <a href="{{ route('users.show', $user) }}" class="text-gray-700 hover:text-amber-600 transition font-medium">
                                    {{ $user->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $roleColors = [
                                        'developer' => 'bg-purple-100 text-purple-700 border-purple-200',
                                        'admin' => 'bg-red-100 text-red-700 border-red-200',
                                        'lawyer' => 'bg-amber-100 text-amber-600 border-amber-300',
                                        'staff' => 'bg-blue-100 text-blue-700 border-blue-200',
                                        'client' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    ];
                                    $roleLabels = [
                                        'developer' => __('app.developer'),
                                        'admin' => __('app.admin'),
                                        'lawyer' => __('app.lawyer'),
                                        'staff' => __('app.staff'),
                                        'client' => __('app.client_role'),
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $roleColors[$user->role] ?? 'bg-gray-100 text-gray-500 border-gray-200' }}">
                                    {{ $roleLabels[$user->role] ?? $user->role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-700" dir="ltr">{{ $user->phone ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if($user->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 border border-emerald-200">{{ __('app.active') }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">{{ __('app.inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors" title="{{ __('app.edit') }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                                    </a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="contents" onsubmit="return confirm('{{ __("app.confirm_delete") }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors" title="{{ __('app.delete') }}">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">{{ __('app.no_users') }}</td>
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
