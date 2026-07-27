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
                @php $other = $conv->participants->where('id', '!=', auth()->id())->first(); @endphp
                <a href="{{ route('chat.show', $conv) }}" class="block px-4 py-3 border-b border-ivory/5 hover:bg-white/[0.02] transition {{ isset($conversation) && $conversation->id === $conv->id ? 'bg-gold/5 border-r-2 border-r-gold' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-gold-dark flex items-center justify-center text-navy font-bold text-sm flex-shrink-0">
                                {{ $other?->name[0] ?? '?' }}
                            </div>
                            @if($conv->unread_count > 0)
                                <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center">{{ $conv->unread_count > 9 ? '9+' : $conv->unread_count }}</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-white font-medium truncate">
                                    {{ $other?->name ?? 'مجموعة' }}
                                </p>
                                @if($conv->lastMessage)
                                    <span class="text-[10px] text-white/30 flex-shrink-0">{{ $conv->lastMessage->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            <p class="text-xs text-white/40 truncate mt-0.5">
                                @if($conv->lastMessage)
                                    <span class="text-gold/60">{{ $conv->lastMessage->user->name }}: </span>
                                    @if($conv->lastMessage->attachment_path && !$conv->lastMessage->message)
                                        <span class="text-white/30">📎 مرفق</span>
                                    @else
                                        {{ $conv->lastMessage->message }}
                                    @endif
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
            {{-- Chat Header --}}
            @php $other = $conversation->participants->where('id', '!=', auth()->id())->first(); @endphp
            <div class="px-4 py-3 border-b border-ivory/5 flex items-center gap-3 bg-navy-light/30">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-gold to-gold-dark flex items-center justify-center text-navy font-bold text-sm flex-shrink-0">
                    {{ $other?->name[0] ?? '?' }}
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">{{ $other?->name ?? 'المحادثة' }}</h3>
                    <p class="text-[11px] text-white/30">{{ $other?->role ?? '' }}</p>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chatMessages">
                @foreach($messages as $msg)
                    <div class="flex {{ $msg->user_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[75%] {{ $msg->user_id === auth()->id() ? 'bg-gold/15 border-gold/20' : 'bg-white/5 border-white/10' }} rounded-2xl px-4 py-2.5 border">
                            @if($msg->user_id !== auth()->id())
                                <p class="text-[11px] text-gold/60 font-medium mb-1">{{ $msg->user->name }}</p>
                            @endif
                            @if($msg->message)
                                <p class="text-sm text-white/80">{{ $msg->message }}</p>
                            @endif
                            @if($msg->attachment_path)
                                @if($msg->is_image)
                                    <div class="mt-2 rounded-xl overflow-hidden border border-white/10">
                                        <img src="{{ $msg->attachment_url }}" alt="{{ $msg->attachment_name }}" class="max-w-full h-auto cursor-pointer" onclick="window.open(this.src, '_blank')">
                                    </div>
                                @else
                                    <div class="mt-2">
                                        <a href="{{ $msg->attachment_url }}" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 transition text-xs text-white/60">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                            <span class="truncate">{{ $msg->attachment_name }}</span>
                                        </a>
                                    </div>
                                @endif
                            @endif
                            <p class="text-[10px] text-white/30 mt-1 text-left">{{ $msg->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @endforeach
                <div id="chatScrollAnchor"></div>
            </div>

            {{-- Input --}}
            <div class="p-4 border-t border-ivory/5 bg-navy-light/20">
                <form id="chatForm" enctype="multipart/form-data">
                    @csrf
                    <div class="flex gap-2">
                        <label class="flex-shrink-0 w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center cursor-pointer hover:bg-gold/10 hover:border-gold/30 transition">
                            <input type="file" id="fileInput" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" class="hidden">
                            <svg class="w-5 h-5 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                            </svg>
                        </label>
                        <div class="flex-1 relative">
                            <input type="text" id="messageInput" placeholder="اكتب رسالة..." autocomplete="off"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:outline-none focus:border-gold/30 focus:bg-gold/[0.02] transition">
                            <div id="filePreview" class="hidden absolute bottom-full mb-2 right-0 left-0 bg-navy border border-ivory/10 rounded-xl p-3 flex items-center gap-3">
                                <span id="fileName" class="text-xs text-white/70 flex-1 truncate"></span>
                                <button type="button" id="clearFile" class="text-red-400 hover:text-red-300 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="bg-gold hover:bg-gold-light text-navy font-bold px-4 py-2.5 rounded-xl text-sm transition flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        @else
            {{-- No conversation selected --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <svg class="w-20 h-20 text-white/10 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
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
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const clearFile = document.getElementById('clearFile');
    const anchor = document.getElementById('chatScrollAnchor');
    const conversationId = {{ $conversation->id }};
    let lastMessageId = {{ $messages->last()?->id ?? 0 }};
    let selectedFile = null;

    function scrollToBottom() {
        anchor?.scrollIntoView({ behavior: 'smooth' });
    }
    scrollToBottom();

    fileInput.addEventListener('change', function() {
        if (this.files.length) {
            selectedFile = this.files[0];
            fileName.textContent = selectedFile.name + ' (' + (selectedFile.size / 1024).toFixed(1) + ' KB)';
            filePreview.classList.remove('hidden');
        }
    });

    clearFile.addEventListener('click', function() {
        selectedFile = null;
        fileInput.value = '';
        filePreview.classList.add('hidden');
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg && !selectedFile) return;

        const formData = new FormData();
        if (msg) formData.append('message', msg);
        if (selectedFile) formData.append('attachment', selectedFile);

        fetch('{{ route('chat.messages.send', $conversation) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: formData
        }).then(r => {
            if (!r.ok) { throw new Error('فشل الإرسال'); }
            return r.json();
        }).then(data => {
            input.value = '';
            selectedFile = null;
            fileInput.value = '';
            filePreview.classList.add('hidden');
            appendMessage(data, true);
            lastMessageId = data.id;
            scrollToBottom();
        }).catch(e => {
            alert('حدث خطأ أثناء الإرسال. تأكد من حجم الملف لا يتجاوز 20MB.');
        });
    });

    function appendMessage(data, isOwn) {
        const div = document.createElement('div');
        div.className = 'flex ' + (isOwn ? 'justify-end' : 'justify-start');

        let attachmentHtml = '';
        if (data.attachment_url) {
            if (data.is_image) {
                attachmentHtml = `<div class="mt-2 rounded-xl overflow-hidden border border-white/10">
                    <img src="${data.attachment_url}" alt="${data.attachment_name || ''}" class="max-w-full h-auto cursor-pointer" onclick="window.open(this.src, '_blank')">
                </div>`;
            } else {
                attachmentHtml = `<div class="mt-2">
                    <a href="${data.attachment_url}" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 transition text-xs text-white/60">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                        <span class="truncate">${data.attachment_name || 'ملف مرفق'}</span>
                    </a>
                </div>`;
            }
        }

        div.innerHTML = `<div class="max-w-[75%] ${isOwn ? 'bg-gold/15 border-gold/20' : 'bg-white/5 border-white/10'} rounded-2xl px-4 py-2.5 border">
            ${!isOwn ? '<p class="text-[11px] text-gold/60 font-medium mb-1">' + data.user_name + '</p>' : ''}
            ${data.message ? '<p class="text-sm text-white/80">' + data.message.replace(/</g, '&lt;') + '</p>' : ''}
            ${attachmentHtml}
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
    }, 5000);
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
