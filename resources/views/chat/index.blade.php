@extends('layouts.app')

@section('title', __('app.chat') ?? 'المحادثات')

@section('content')
<div class="flex gap-4 h-[calc(100vh-10rem)]">
    {{-- Conversations List --}}
    <div class="w-80 flex-shrink-0 bg-navy-light/50 rounded-xl border border-ivory/5 overflow-hidden flex flex-col">
        <div class="p-4 border-b border-ivory/5">
            <h2 class="text-lg font-bold text-gold">{{ __('app.chat') ?? 'المحادثات' }}</h2>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse($conversations as $conv)
                <a href="{{ route('chat.show', $conv) }}" class="block px-4 py-3 border-b border-ivory/5 hover:bg-white/[0.02] transition {{ isset($conversation) && $conversation->id === $conv->id ? 'bg-gold/5 border-r-2 border-r-gold' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-gold-dark flex items-center justify-center text-navy font-bold text-sm flex-shrink-0">
                            {{ $conv->participants->where('id', '!=', auth()->id())->first()?->name[0] ?? '?' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-white font-medium truncate">
                                    {{ $conv->participants->where('id', '!=', auth()->id())->first()?->name ?? 'مجموعة' }}
                                </p>
                                @if($conv->lastMessage)
                                    <span class="text-[10px] text-white/30 flex-shrink-0">{{ $conv->lastMessage->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-white/40 truncate mt-0.5">
                                @if($conv->lastMessage)
                                    <span class="text-gold/60">{{ $conv->lastMessage->user->name }}: </span>{{ $conv->lastMessage->message }}
                                @else
                                    <span class="text-white/20">لا توجد رسائل</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-8 text-center text-white/30 text-sm">لا توجد محادثات</div>
            @endforelse
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex-1 bg-navy-light/50 rounded-xl border border-ivory/5 overflow-hidden flex flex-col">
        @if(isset($conversation))
            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chatMessages">
                @foreach($messages as $msg)
                    <div class="flex {{ $msg->user_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[70%] {{ $msg->user_id === auth()->id() ? 'bg-gold/15 border-gold/20' : 'bg-white/5 border-white/10' }} rounded-2xl px-4 py-2.5 border">
                            @if($msg->user_id !== auth()->id())
                                <p class="text-[11px] text-gold/60 font-medium mb-1">{{ $msg->user->name }}</p>
                            @endif
                            <p class="text-sm text-white/80">{{ $msg->message }}</p>
                            <p class="text-[10px] text-white/30 mt-1 text-left">{{ $msg->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @endforeach
                <div id="chatScrollAnchor"></div>
            </div>

            {{-- Input --}}
            <div class="p-4 border-t border-ivory/5">
                <form id="chatForm" class="flex gap-3">
                    @csrf
                    <input type="text" id="messageInput" placeholder="اكتب رسالة..." autocomplete="off"
                        class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:outline-none focus:border-gold/30 focus:bg-gold/[0.02] transition">
                    <button type="submit" class="bg-gold hover:bg-gold-light text-navy font-bold px-5 py-2.5 rounded-xl text-sm transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-7 7m7-7l7 7"/></svg>
                    </button>
                </form>
            </div>
        @else
            {{-- No conversation selected --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <svg class="w-16 h-16 text-white/10 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <h3 class="text-white/30 font-medium text-lg">{{ __('app.chat') ?? 'المحادثات' }}</h3>
                    <p class="text-white/20 text-sm mt-1">اختر محادثة أو ابدأ محادثة جديدة مع موظف</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Users List (new conversation) --}}
    <div class="w-72 flex-shrink-0 bg-navy-light/50 rounded-xl border border-ivory/5 overflow-hidden flex flex-col">
        <div class="p-4 border-b border-ivory/5">
            <h3 class="text-sm font-bold text-gold">{{ __('app.chat_users') ?? 'الموظفون' }}</h3>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse($users as $user)
                <form method="POST" action="{{ route('chat.store') }}" class="block">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                    <input type="hidden" name="message" value="مرحباً">
                    <button type="submit" class="w-full text-right px-4 py-3 border-b border-ivory/5 hover:bg-white/[0.02] transition flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-gold to-gold-dark flex items-center justify-center text-navy font-bold text-sm flex-shrink-0">
                            {{ $user->name[0] }}
                        </div>
                        <div>
                            <p class="text-sm text-white/70">{{ $user->name }}</p>
                            <p class="text-xs text-white/30">{{ $user->role }}</p>
                        </div>
                    </button>
                </form>
            @empty
                <div class="p-8 text-center text-white/30 text-sm">لا يوجد موظفون آخرون</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(isset($conversation))
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    const messagesEl = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('messageInput');
    const anchor = document.getElementById('chatScrollAnchor');
    const conversationId = {{ $conversation->id }};
    let lastMessageId = {{ $messages->last()?->id ?? 0 }};

    function scrollToBottom() {
        anchor?.scrollIntoView({ behavior: 'smooth' });
    }
    scrollToBottom();

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg) return;

        fetch('{{ route('chat.messages.send', $conversation) }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ message: msg })
        }).then(r => r.json()).then(data => {
            input.value = '';
            appendMessage(data, true);
            lastMessageId = data.id;
            scrollToBottom();
        }).catch(() => {});
    });

    function appendMessage(data, isOwn) {
        const div = document.createElement('div');
        div.className = 'flex ' + (isOwn ? 'justify-end' : 'justify-start');
        div.innerHTML = `<div class="max-w-[70%] ${isOwn ? 'bg-gold/15 border-gold/20' : 'bg-white/5 border-white/10'} rounded-2xl px-4 py-2.5 border">
            ${!isOwn ? '<p class="text-[11px] text-gold/60 font-medium mb-1">' + data.user_name + '</p>' : ''}
            <p class="text-sm text-white/80">${data.message.replace(/</g, '&lt;')}</p>
            <p class="text-[10px] text-white/30 mt-1 text-left">${data.created_at}</p>
        </div>`;
        messagesEl.insertBefore(div, anchor);
    }

    setInterval(function() {
        fetch('{{ route('chat.messages.fetch', $conversation) }}?after=' + lastMessageId, {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
        }).then(r => r.json()).then(data => {
            if (data.length) {
                data.forEach(m => { appendMessage(m, m.user_id === {{ auth()->id() }}); lastMessageId = m.id; });
                scrollToBottom();
            }
        }).catch(() => {});
    }, 3000);
});
</script>
@endif

<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    function updateUnread() {
        fetch('{{ route('chat.unread') }}').then(r => r.json()).then(data => {
            const badge = document.getElementById('chatUnreadBadge');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }).catch(() => {});
    }
    updateUnread();
    setInterval(updateUnread, 10000);
});
</script>
@endpush
