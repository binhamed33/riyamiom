@extends('layouts.app')

@section('title', __('app.page_notifications'))

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gold">{{ __('app.notifications') }}</h1>
        @if(isset($notifications) && $notifications->count() > 0)
            <form method="POST" action="{{ route('notifications.readAll') }}">
                @csrf
                <button type="submit" class="bg-white/5 border border-gold/30 text-gold px-5 py-2 rounded-lg hover:bg-gold/10 transition text-sm">{{ __('app.mark_all_read') }}</button>
            </form>
        @endif
    </div>

    <div class="bg-navy-light rounded-xl border border-ivory/10 overflow-hidden">
        @forelse($notifications ?? [] as $notification)
            <div class="px-6 py-4 border-b border-ivory/5 {{ $notification->is_read ? 'bg-transparent' : 'bg-gold/5' }} hover:bg-navy-lighter/50 transition">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 mt-1">
                        @if(!$notification->is_read)
                            <span class="w-2.5 h-2.5 rounded-full bg-gold block"></span>
                        @else
                            <span class="w-2.5 h-2.5 rounded-full bg-ivory/10 block"></span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-ivory font-medium {{ $notification->is_read ? 'text-ivory/60' : '' }}">
                            {{ $notification->title ?? __('app.notification_default') }}
                        </p>
                        @if($notification->message)
                            <p class="text-ivory/50 text-sm mt-1">{{ $notification->message }}</p>
                        @endif
                        <p class="text-ivory/30 text-xs mt-2">{{ $notification->created_at?->diffForHumans() ?? '—' }}</p>
                    </div>
                    @if(!$notification->is_read)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="text-ivory/30 hover:text-gold transition text-xs whitespace-nowrap">{{ __('app.mark_as_read') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <svg class="w-12 h-12 text-ivory/10 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p class="text-ivory/30 text-lg">{{ __('app.no_notifications') }}</p>
            </div>
        @endforelse
    </div>

    @if(isset($notifications) && method_exists($notifications, 'links'))
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection