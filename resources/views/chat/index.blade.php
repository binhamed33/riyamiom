@extends('layouts.app')

@section('title', __('app.chat') ?? 'المحادثات')

@php
function roleAvatar($user, $size = 9) {
    $g = ['developer'=>'from-purple-600 to-purple-800','admin'=>'from-red-500 to-red-700','lawyer'=>'from-blue-500 to-blue-700','staff'=>'from-emerald-500 to-emerald-700'];
    $i = ['developer'=>'<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>','admin'=>'<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2l7 3v6c0 6.5-4.5 10.5-7 12-2.5-1.5-7-5.5-7-12V5l7-3z"/></svg>','lawyer'=>'<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>','staff'=>'<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'];
    $role = $user->role ?? 'staff';
    $c = $size === 10 ? 'w-10 h-10' : 'w-9 h-9';
    return '<div class="' . $c . ' rounded-full bg-gradient-to-br ' . ($g[$role]??$g['staff']) . ' flex items-center justify-center flex-shrink-0">' . ($i[$role]??$i['staff']) . '</div>';
}
@endphp
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
                            {!! $other ? roleAvatar($other, 10) : '<div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0"><span class="text-white/50 font-bold text-sm">?</span></div>' !!}
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
                {!! $other ? roleAvatar($other) : '<div class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0"><span class="text-white/50 font-bold text-sm">?</span></div>' !!}
                <div>
                    <h3 class="text-sm font-bold text-white">{{ $other?->name ?? 'المحادثة' }}</h3>
                    <p class="text-[11px] text-white/30">{{ $other?->role ?? '' }}</p>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chatMessages">
                @foreach($messages as $msg)
                    @php $isOwn = $msg->user_id === auth()->id(); $escMsg = htmlspecialchars($msg->message, ENT_QUOTES, 'UTF-8'); @endphp
                    <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }} group" data-message-id="{{ $msg->id }}">
                        <div class="max-w-[75%] {{ $isOwn ? 'bg-gold/15 border-gold/20' : 'bg-white/5 border-white/10' }} rounded-2xl px-4 py-2.5 border relative">
                            @if(!$isOwn)
                                <p class="text-[11px] text-gold/60 font-medium mb-1" data-sender-name="{{ $msg->user->name }}">{{ $msg->user->name }}</p>
                            @endif
                            @if($msg->replyTo)
                                <div class="text-[11px] text-white/40 mb-1.5 pr-2 border-r-2 border-gold/30 py-0.5 truncate">
                                    <span class="text-gold/50">رد:</span> {{ $msg->replyTo->message }}
                                </div>
                            @endif
                            @if($msg->message)
                                <p class="text-sm text-white/80" data-message-text="{{ $escMsg }}">{{ $msg->message }}
                                    @if($msg->edited_at) <span class="text-[10px] text-white/30">(تم التعديل)</span> @endif
                                </p>
                            @endif
                            @if($msg->attachment_path)
                                @if($msg->is_image)
                                    <div class="mt-2 rounded-xl overflow-hidden border border-white/10 bg-black/20">
                                        <img src="{{ $msg->attachment_url }}" alt="{{ $msg->attachment_name }}" class="max-w-full h-auto">
                                    </div>
                                @endif
                                <div class="mt-2">
                                    <a href="{{ $msg->attachment_url }}" download="{{ $msg->attachment_name }}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gold/10 hover:bg-gold/20 transition text-xs text-gold">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="truncate">{{ $msg->attachment_name }}</span>
                                    </a>
                                </div>
                            @endif
                            <div class="flex items-center justify-between mt-1">
                                <p class="text-[10px] text-white/30">{{ $msg->created_at->diffForHumans() }}</p>
                                <div class="flex gap-1.5 opacity-0 group-hover:opacity-100 transition">
                                    @if($isOwn)
                                        <button type="button" data-msg-action="edit" class="text-[10px] text-gold/50 hover:text-gold transition" title="تعديل">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button type="button" data-msg-action="delete" class="text-[10px] text-red-400/50 hover:text-red-400 transition" title="حذف">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                    <button type="button" data-msg-action="reply" class="text-[10px] text-white/30 hover:text-gold transition" title="رد">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div id="chatScrollAnchor"></div>
            </div>

            {{-- Reply Bar + Input --}}
            <div class="border-t border-ivory/5 bg-navy-light/20">
                <div id="replyBar" class="hidden px-4 py-2 bg-gold/5 border-b border-gold/10 flex items-center gap-3">
                    <svg class="w-4 h-4 text-gold/50 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] text-gold/60"><span id="replyUserName"></span></p>
                        <p class="text-xs text-white/50 truncate" id="replyMessageText"></p>
                    </div>
                    <button type="button" id="cancelReply" class="text-red-400/50 hover:text-red-400 transition flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-4">
                    <form id="chatForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" id="replyToId" name="reply_to_id" value="">
                        <div class="flex gap-2">
                            <label class="flex-shrink-0 w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center cursor-pointer hover:bg-gold/10 hover:border-gold/30 transition">
                                <input type="file" id="fileInput" name="attachment" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar" class="hidden">
                                <svg class="w-5 h-5 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
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
                    <button type="submit" class="w-full text-right px-4 py-3 border-b border-ivory/5 hover:bg-white/[0.02] transition flex items-center gap-3">
                        {!! roleAvatar($user) !!}
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

{{-- Custom Confirm Modal --}}
<div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-gradient-to-br from-navy-light to-navy border border-ivory/10 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl shadow-black/40">
        <div class="text-center">
            <svg class="w-14 h-14 mx-auto mb-4 text-gold/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p id="confirmMessage" class="text-white/90 text-base font-medium mb-6 leading-relaxed"></p>
            <div class="flex gap-3 justify-center">
                <button id="confirmYes" class="px-7 py-2.5 bg-gradient-to-l from-gold to-gold-light hover:from-gold-light hover:to-gold text-navy-dark font-bold rounded-xl transition-all shadow-lg shadow-gold/20 hover:shadow-gold/30 text-sm">تأكيد</button>
                <button id="confirmNo" class="px-7 py-2.5 bg-white/5 hover:bg-white/10 text-white/60 hover:text-white/80 border border-white/10 rounded-xl transition text-sm">إلغاء</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(isset($conversation))
<script nonce="{{ $cspNonce }}">
function showConfirm(message) {
    return new Promise(resolve => {
        const modal = document.getElementById('confirmModal');
        const msgEl = document.getElementById('confirmMessage');
        const yesBtn = document.getElementById('confirmYes');
        const noBtn = document.getElementById('confirmNo');
        if (!modal || !msgEl || !yesBtn || !noBtn) { resolve(true); return; }
        msgEl.textContent = message;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        function cleanup(result) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            yesBtn.removeEventListener('click', onYes);
            noBtn.removeEventListener('click', onNo);
            modal.removeEventListener('click', onOverlay);
            resolve(result);
        }
        function onYes() { cleanup(true); }
        function onNo() { cleanup(false); }
        function onOverlay(e) { if (e.target === modal) cleanup(false); }
        yesBtn.addEventListener('click', onYes);
        noBtn.addEventListener('click', onNo);
        modal.addEventListener('click', onOverlay);
    });
}

window.chatActions = {
    editMsg: function(container) {
        const bubble = container.querySelector(':scope > div');
        const textEl = bubble.querySelector('p.text-sm');
        const oldText = textEl ? textEl.textContent.replace(/\(تم التعديل\)/g, '').trim() : '';
        const editHtml = `
            <div class="edit-form mt-1">
                <textarea class="w-full bg-white/5 border border-gold/30 rounded-lg px-3 py-2 text-sm text-white placeholder-white/20 focus:outline-none focus:border-gold resize-none" rows="2">${oldText.replace(/</g, '&lt;')}</textarea>
                <div class="flex gap-2 mt-1">
                    <button type="button" class="edit-save text-[11px] bg-gold text-navy font-bold px-3 py-1 rounded-lg hover:bg-gold-light transition">حفظ</button>
                    <button type="button" class="edit-cancel text-[11px] text-white/50 hover:text-white transition">إلغاء</button>
                </div>
            </div>`;
        const timeRow = bubble.querySelector('.flex.items-center');
        if (textEl) textEl.style.display = 'none';
        if (timeRow) timeRow.style.display = 'none';
        if (textEl) {
            textEl.insertAdjacentHTML('afterend', editHtml);
        } else {
            bubble.insertAdjacentHTML('beforeend', editHtml);
        }
        const editForm = bubble.querySelector('.edit-form');
        const textarea = editForm.querySelector('textarea');
        const msgId = container.dataset.messageId;
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        editForm.querySelector('.edit-save').addEventListener('click', async function() {
            const newText = textarea.value.trim();
            if (!newText) return;
            const confirmed = await showConfirm('هل انت متأكد من تعديل الرسالة؟');
            if (!confirmed) return;
            fetch('{{ url('chat/messages') }}/' + msgId, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify({ message: newText })
            }).then(r => { if (!r.ok) throw new Error(); return r.json(); })
            .then(data => {
                if (textEl) {
                    textEl.textContent = newText + ' ';
                    let editedSpan = textEl.querySelector('span');
                    if (!editedSpan) { editedSpan = document.createElement('span'); textEl.appendChild(editedSpan); }
                    editedSpan.className = 'text-[10px] text-white/30';
                    editedSpan.textContent = '(تم التعديل)';
                    textEl.style.display = '';
                }
                if (timeRow) timeRow.style.display = '';
                editForm.remove();
            }).catch(() => { alert('فشل التعديل'); });
        });
        editForm.querySelector('.edit-cancel').addEventListener('click', function() {
            if (textEl) textEl.style.display = '';
            if (timeRow) timeRow.style.display = '';
            editForm.remove();
        });
    },
    deleteMsg: async function(msgId, container) {
        const confirmed = await showConfirm('هل انت متأكد من حذف الرسالة؟');
        if (!confirmed) return;
        fetch('{{ url('chat/messages') }}/' + msgId, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
        }).then(r => { if (!r.ok) throw new Error(); return r.json(); })
        .then(data => { container.remove(); })
        .catch(() => { alert('فشل الحذف'); });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const messagesEl = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('messageInput');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const clearFile = document.getElementById('clearFile');
    const anchor = document.getElementById('chatScrollAnchor');
    const replyBar = document.getElementById('replyBar');
    const replyToId = document.getElementById('replyToId');
    const replyUserName = document.getElementById('replyUserName');
    const replyMessageText = document.getElementById('replyMessageText');
    const cancelReply = document.getElementById('cancelReply');
    const conversationId = {{ $conversation->id }};
    let lastMessageId = {{ $messages->last()?->id ?? 0 }};
    let selectedFile = null;

    function scrollToBottom() {
        anchor?.scrollIntoView({ behavior: 'smooth' });
    }
    scrollToBottom();

    // Reply bar
    window.setReply = function(id, msg, name) {
        replyToId.value = id;
        replyUserName.textContent = 'رد على ' + name + ':';
        replyMessageText.textContent = msg || 'مرفق';
        replyBar.classList.remove('hidden');
        input.focus();
    };
    cancelReply.addEventListener('click', function() {
        replyToId.value = '';
        replyBar.classList.add('hidden');
    });

    // Double-click to reply
    messagesEl.addEventListener('dblclick', function(e) {
        const container = e.target.closest('[data-message-id]');
        if (!container) return;
        const id = container.dataset.messageId;
        const nameEl = container.querySelector('[data-sender-name]');
        const textEl = container.querySelector('[data-message-text]');
        const name = nameEl ? nameEl.dataset.senderName : '';
        const msg = textEl ? textEl.dataset.messageText : '';
        setReply(id, msg, name);
    });

    // Event delegation for action buttons (CSP-safe)
    messagesEl.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-msg-action]');
        if (!btn) return;
        const action = btn.dataset.msgAction;
        const container = btn.closest('[data-message-id]');
        if (!container) return;
        const msgId = container.dataset.messageId;

        if (action === 'edit') {
            chatActions.editMsg(container);
        } else if (action === 'delete') {
            chatActions.deleteMsg(msgId, container);
        } else if (action === 'reply') {
            const nameEl = container.querySelector('[data-sender-name]');
            const textEl = container.querySelector('[data-message-text]');
            const name = nameEl ? nameEl.dataset.senderName : '';
            const msg = textEl ? textEl.dataset.messageText : '';
            setReply(msgId, msg, name);
        }
    });

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
        if (replyToId.value) formData.append('reply_to_id', replyToId.value);

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
            cancelReply.click();
            appendMessage(data, true);
            lastMessageId = data.id;
            scrollToBottom();
        }).catch(e => {
            alert('حدث خطأ أثناء الإرسال. تأكد من حجم الملف لا يتجاوز 20MB.');
        });
    });

    function appendMessage(data, isOwn) {
        const div = document.createElement('div');
        div.className = 'flex ' + (isOwn ? 'justify-end' : 'justify-start') + ' group';
        div.dataset.messageId = data.id;

        if (!isOwn) {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 520;
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
                osc.type = 'sine';
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.3);
            } catch(_) {}
        }

        let attachmentHtml = '';
        if (data.attachment_url) {
            if (data.is_image) {
                attachmentHtml += `<div class="mt-2 rounded-xl overflow-hidden border border-white/10 bg-black/20">
                    <img src="${data.attachment_url}" alt="${data.attachment_name || ''}" class="max-w-full h-auto">
                </div>`;
            }
            attachmentHtml += `<div class="mt-2">
                <a href="${data.attachment_url}" download="${data.attachment_name || 'download'}" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gold/10 hover:bg-gold/20 transition text-xs text-gold">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="truncate">${data.attachment_name || 'ملف مرفق'}</span>
                </a>
            </div>`;
        }

        const replyHtml = data.reply_message ? `<div class="text-[11px] text-white/40 mb-1.5 pr-2 border-r-2 border-gold/30 py-0.5 truncate">
            <span class="text-gold/50">رد:</span> ${data.reply_message.replace(/</g, '&lt;')}
        </div>` : '';

        const editedHtml = data.edited_at ? '<span class="text-[10px] text-white/30">(تم التعديل)</span>' : '';

        const senderNameDisplay = !isOwn ? `<p class="text-[11px] text-gold/60 font-medium mb-1" data-sender-name="${data.user_name.replace(/"/g, '&quot;')}">${data.user_name}</p>` : '';

        const actionsHtml = `<div class="flex items-center justify-between mt-1">
            <p class="text-[10px] text-white/30">${data.created_at}</p>
            <div class="flex gap-1.5 opacity-0 group-hover:opacity-100 transition">
                ${isOwn ? `
                    <button type="button" data-msg-action="edit" class="text-[10px] text-gold/50 hover:text-gold transition" title="تعديل">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button type="button" data-msg-action="delete" class="text-[10px] text-red-400/50 hover:text-red-400 transition" title="حذف">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                ` : ''}
                <button type="button" data-msg-action="reply" class="text-[10px] text-white/30 hover:text-gold transition" title="رد">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </button>
            </div>
        </div>`;

        div.innerHTML = `<div class="max-w-[75%] ${isOwn ? 'bg-gold/15 border-gold/20' : 'bg-white/5 border-white/10'} rounded-2xl px-4 py-2.5 border relative">
            ${senderNameDisplay}
            ${replyHtml}
            ${data.message ? '<p class="text-sm text-white/80" data-message-text="' + data.message.replace(/"/g, '&quot;') + '">' + data.message.replace(/</g, '&lt;') + ' ' + editedHtml + '</p>' : ''}
            ${attachmentHtml}
            ${actionsHtml}
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
