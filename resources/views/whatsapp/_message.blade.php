@php
    /* ══ فقاعةُ رسالةٍ واحدة ══
       تُستدعى من whatsapp/show.blade.php لكل رسالة، وترث من نطاقه:
       ‏$conversation و$canSend و$canManage. والحُرّاس هنا (?? ) ليست
       زينةً: لو ضُمّنت هذه الجزئيّة يوماً من صفحةٍ أخرى لا تعرّف تلك
       المتغيّرات لسقطت الصفحة كلّها بخطأ متغيّرٍ غير معرَّف. */
    $waConv = $conversation ?? null;
    $waCanSend = $canSend ?? false;

    $isNote = (bool) $message->is_internal;
    $inbound = $message->isInbound();
    $failed = $message->status === \App\Models\WhatsAppMessage::STATUS_FAILED;

    /* ══ المحاذاة منطقيّة لا فيزيائيّة ══
       في العربية يقع الوارد يميناً والصادر يساراً — كما في كل تطبيق
       محادثة معرَّب — وفي الإنجليزية ينعكس الاثنان. ولو ثُبّتت
       ‏left/right لظهر الوارد في الجهة الخاطئة عند تبديل اللغة. */
    $align = $isNote ? 'justify-center' : ($inbound ? 'justify-start' : 'justify-end');

    $waAt = $message->sent_at ?? $message->created_at;

    /* اسم المُرسِل يُقرأ من العلاقة المحمَّلة وحدها: خيطٌ فيه مئتا
       رسالة كان يعني مئتي استعلامٍ لأسماء أربعة موظّفين. */
    $waSender = $message->relationLoaded('sender') ? $message->sender?->name : null;

    $waTypeLabel = match ($message->type) {
        'image' => __('app.wa_media_image'),
        'document' => __('app.wa_media_document'),
        'audio' => __('app.wa_media_audio'),
        'video' => __('app.wa_media_video'),
        'sticker' => __('app.wa_media_sticker'),
        default => __('app.wa_media_file'),
    };

    /* ══ جسمُ رسالةِ القالب ليس نصّاً ══
       المتحكّم يحفظ في body قيمَ متغيّرات القالب مُرقَّمةً بـJSON، لا
       الجملةَ التي وصلت العميل. ورسمُه خاماً يُري المحاميَ سطراً مثل
       ‏["أحمد","2026-09-01"] في خيطه. تُفكّ هنا وتُعرض قيماً مقروءة. */
    $waTplParams = null;
    if (filled($message->template_name) && filled($message->body)) {
        $decoded = json_decode((string) $message->body, true);
        if (is_array($decoded) && $decoded !== []) {
            $waTplParams = array_values($decoded);
        }
    }

    $waSize = null;
    if ($message->media_size > 0) {
        $waSize = $message->media_size >= 1048576
            ? round($message->media_size / 1048576, 1) . ' MB'
            : max(1, (int) round($message->media_size / 1024)) . ' KB';
    }
@endphp

<div class="flex {{ $align }}">
    <div class="max-w-[85%] sm:max-w-[75%] min-w-0">

        @if($isNote)

            {{-- ══ ملاحظة داخلية ══
                 تعيش في الخيط كي يقرأها الفريق في سياقها، ولا تُرسل
                 أبداً. اللون الكهرماني والوسمُ الصريح هما الحاجز الأخير:
                 من يكتب رأيه في الموكّل ثم لا يميّز الملاحظةَ من الردّ
                 يرسله إليه. --}}
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5">
                <div class="flex items-center gap-1.5 mb-1">
                    <svg class="w-3.5 h-3.5 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/>
                    </svg>
                    <span class="text-[11px] font-bold text-amber-800">{{ __('app.wa_internal_note') }}</span>
                    @if($waSender)
                        <span class="text-[11px] text-amber-700">· {{ $waSender }}</span>
                    @endif
                </div>
                <p class="text-sm text-amber-900 leading-relaxed whitespace-pre-wrap break-words" style="overflow-wrap: anywhere;">{{ $message->body }}</p>
                <p class="text-[10px] text-amber-700/80 mt-1.5">{{ __('app.wa_note_never_sent') }}</p>
            </div>

        @else

            <div class="rounded-2xl px-4 py-2.5 border
                        {{ $inbound
                            ? 'bg-white border-gray-200'
                            : ($failed ? 'bg-red-50 border-red-200' : 'bg-gold/12 border-gold/25') }}">

                @if($message->template_name)
                    {{-- القالبُ نصٌّ اعتمدته Meta لا كلامَ الموظّف: تمييزُه
                         يمنع الظنّ بأنّ صياغته قابلة للتعديل هنا. --}}
                    <div class="flex items-center gap-1.5 mb-1.5 pb-1.5 border-b border-gray-200/60">
                        <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                        <span class="text-[11px] font-semibold text-gray-500 truncate" dir="ltr">{{ $message->template_name }}</span>
                    </div>
                @endif

                @if($waTplParams !== null)
                    <ul class="space-y-1">
                        @foreach($waTplParams as $waIdx => $waParam)
                            <li class="flex items-start gap-2 text-sm text-gray-800">
                                <span class="flex-shrink-0 text-[10px] font-bold text-gray-400 mt-1" dir="ltr">{{ $waIdx + 1 }}</span>
                                <span class="min-w-0 break-words" style="overflow-wrap: anywhere;">{{ is_scalar($waParam) ? $waParam : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @elseif(filled($message->body))
                    {{-- ‏{{ }} لا {!! !!} أبداً: الجسم يكتبه من في الطرف
                         الآخر. وسمُ <script> في رسالةٍ واردة يصير شيفرةً
                         تعمل في متصفّح المحامي لو رُسم خاماً. --}}
                    <p class="text-sm leading-relaxed whitespace-pre-wrap break-words
                              {{ $inbound ? 'text-gray-800' : ($failed ? 'text-red-800' : 'text-gray-800') }}"
                       style="overflow-wrap: anywhere;">{{ $message->body }}</p>
                @endif

                @if($message->hasMedia())
                    <div class="mt-2 flex items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            @switch($message->type)
                                @case('image')
                                @case('sticker')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                    @break
                                @case('audio')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                                    @break
                                @case('video')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/>
                                    @break
                                @default
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                            @endswitch
                        </svg>

                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-semibold text-gray-700 truncate" title="{{ $message->media_name ?: $waTypeLabel }}">
                                {{ $message->media_name ?: $waTypeLabel }}
                            </p>
                            <p class="text-[11px] text-gray-400">
                                {{ $waTypeLabel }}@if($waSize) · <span dir="ltr">{{ $waSize }}</span>@endif
                            </p>
                        </div>

                        @if($message->document_id)
                            {{-- حُفظ في ملفّ القضيّة: الرابطُ إلى النسخة عندنا
                                 لا إلى Meta — روابطُ وسائط Meta تنتهي صلاحيّتها
                                 خلال أيام، فرابطٌ إليها يموت بلا إنذار. --}}
                            <a href="{{ route('documents.preview', $message->document_id) }}"
                               class="flex-shrink-0 inline-flex items-center gap-1 text-[11px] font-bold text-gold-dark hover:underline underline-offset-4 whitespace-nowrap">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ __('app.wa_open_document') }}
                            </a>
                        @elseif($waCanSend)
                            @if($waConv?->case_id)
                                <form method="POST" action="{{ route('whatsapp.save-document', $message) }}" class="flex-shrink-0">
                                    @csrf
                                    {{-- المتحكّم يشترط case_id في الطلب ولا
                                         يستنبطه من المحادثة: يتحقّق أنّ
                                         القضيّة قضيّةُ موكّلِ هذه المحادثة،
                                         فلا يمرّ معرّفٌ من ملفّ موكّلٍ آخر.
                                         يُرسَل هنا معرّفُ القضيّة المربوطة. --}}
                                    <input type="hidden" name="case_id" value="{{ $waConv->case_id }}">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-gray-600 hover:text-gold-dark hover:border-gold/30 transition-colors whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                        </svg>
                                        {{ __('app.wa_save_document') }}
                                    </button>
                                </form>
                            @else
                                {{-- «حفظٌ في القضيّة» بلا قضيّةٍ مربوطة زرٌّ
                                     يُرفض دائماً. يُقال السببُ بدل عرضِ
                                     بابٍ مغلق. --}}
                                <span class="flex-shrink-0 text-[11px] text-gray-400 whitespace-nowrap">{{ __('app.wa_link_case_first') }}</span>
                            @endif
                        @endif
                    </div>
                @endif

                {{-- ══ السطر السفليّ: الوقت والحالة ══ --}}
                <div class="mt-1.5 flex items-center gap-2 {{ $inbound ? 'justify-start' : 'justify-end' }}">
                    @if(!$inbound && $waSender)
                        <span class="text-[10px] text-gray-400 truncate max-w-[8rem]">{{ $waSender }}</span>
                    @endif

                    <span class="text-[10px] text-gray-400 whitespace-nowrap" dir="ltr"
                          title="{{ $waAt?->format('Y-m-d H:i') }}">{{ $waAt?->format('H:i') ?? '' }}</span>

                    @unless($inbound)
                        @switch($message->status)
                            @case(\App\Models\WhatsAppMessage::STATUS_QUEUED)
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 whitespace-nowrap">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ __('app.wa_status_queued') }}
                                </span>
                                @break

                            @case(\App\Models\WhatsAppMessage::STATUS_SENT)
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-400 whitespace-nowrap" title="{{ __('app.wa_status_sent') }}">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.6" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    {{ __('app.wa_status_sent') }}
                                </span>
                                @break

                            @case(\App\Models\WhatsAppMessage::STATUS_DELIVERED)
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-500 whitespace-nowrap" title="{{ __('app.wa_status_delivered') }}">
                                    <svg class="w-4 h-3" viewBox="0 0 28 18" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 10l5 5 9-13"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 10l5 5 9-13"/>
                                    </svg>
                                    {{ __('app.wa_status_delivered') }}
                                </span>
                                @break

                            @case(\App\Models\WhatsAppMessage::STATUS_READ)
                                {{-- «مقروءة» بلون هوية المكتب لا بالأزرق:
                                     الأزرقُ علامةُ تطبيقٍ آخر، والذهبيّ هو
                                     ما تقرأه العينُ هنا إشارةَ تمام. --}}
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-gold-dark whitespace-nowrap" title="{{ __('app.wa_status_read') }}">
                                    <svg class="w-4 h-3" viewBox="0 0 28 18" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 10l5 5 9-13"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 10l5 5 9-13"/>
                                    </svg>
                                    {{ __('app.wa_status_read') }}
                                </span>
                                @break

                            @case(\App\Models\WhatsAppMessage::STATUS_FAILED)
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-red-700 whitespace-nowrap">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                    </svg>
                                    {{ __('app.wa_status_failed') }}
                                </span>
                                @break
                        @endswitch
                    @endunless
                </div>

                @if($failed)
                    {{-- ══ سببُ الفشل يُقال لا يُخفى ══
                         «فشل الإرسال» وحدها لا تُصلح شيئاً. عنوانُ خطأ Meta
                         هو ما يميّز «خارج النافذة» من «رقمٌ غير مسجَّل في
                         واتساب» من «رمزٌ انتهت صلاحيّته». --}}
                    <p class="mt-1.5 pt-1.5 border-t border-red-200 text-[11px] text-red-700 break-words" style="overflow-wrap: anywhere;">
                        {{ $message->error_title ?: __('app.wa_error_unknown') }}
                        @if($message->error_code)
                            <span class="text-red-600/70" dir="ltr">({{ $message->error_code }})</span>
                        @endif
                    </p>
                @endif
            </div>

        @endif
    </div>
</div>
