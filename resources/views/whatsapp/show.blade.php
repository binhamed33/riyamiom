@extends('layouts.app')

@section('title', __('app.wa_thread_title'))

@section('content')
@php
    /* ══ الصلاحيات — مرآةٌ لحرّاس المسارات لا اجتهادٌ مستقلّ ══
       المسارات تفتح بالدور أو بالصلاحية: الردّ لمن دوره
       ‏developer/admin/lawyer/employee أو معه whatsapp.send، والإدارة
       لأوّل ثلاثةٍ منها أو معه whatsapp.manage. ولو ضُيّق الشرطُ هنا
       إلى الصلاحية وحدها لاختفى صندوقُ الكتابة عن كل محامٍ وموظّفٍ في
       المكتب — يفتح الخيط ولا يجد به ما يردّ.

       ويُحسب مرّةً واحدة: hasPermission() استعلامٌ في كل نداء،
       والجزئيّةُ أدناه تُستدعى لكل رسالةٍ في خيطٍ قد يبلغ ثلاثمئة. */
    $me = auth()->user();
    // مطابقٌ حرفياً لوسيط المسار: role:developer,admin,permission:whatsapp.*
    //
    // ولولا المطابقة لظهر زرٌّ يقود إلى رفضٍ فوري — وهو أسوأ من غيابه:
    // يُوهم المحامي أنّ له الحقّ ثمّ يُخرجه إلى لوحة التحكّم برسالة منع.
    $canSend   = $me && ($me->isDeveloper() || $me->isAdmin() || (!$me->isClient() && $me->hasPermission('whatsapp.send')));
    $canManage = $me && ($me->isDeveloper() || $me->isAdmin() || (!$me->isClient() && $me->hasPermission('whatsapp.manage')));

    $waContact = $conversation->contact;
    $waName    = $waContact?->displayName() ?: __('app.wa_unknown_contact');
    $waPhone   = $waContact ? \App\Support\GulfPhone::format($waContact->wa_id) : '';
    $waInitial = mb_substr(trim($waName), 0, 1) ?: '#';

    $waOpen     = $conversation->windowOpen();
    $waMinsLeft = $waOpen ? $conversation->windowMinutesLeft() : 0;
    $waClosed   = $conversation->status === \App\Models\WhatsAppConversation::STATUS_CLOSED;

    /* ══ القوالب المعتمَدة وحدها ══
       الاعتماد قرارُ Meta. وعرضُ قالبٍ «قيد المراجعة» في القائمة يعني
       إرسالاً يُرفض بالخطأ 132001 ويرى الموظّف «فشل» بلا سبب — فيُصفّى
       هنا لا في رأس القارئ. */
    $waTemplates = collect($templates ?? [])->filter(fn ($t) => $t->isApproved())->values();
    $waTplData = $waTemplates->map(fn ($t) => [
        'name'  => (string) $t->name,
        'body'  => (string) $t->body,
        'vars'  => (int) $t->variableCount(),
    ])->values()->all();

    /* موظّفو المكتب للإسناد. الحارسُ ليس زينة: لو غابت القائمة يوماً
       بقي «أسنِدها إليّ» يعمل ولم تسقط الصفحة. */
    $waStaff = collect($staff ?? []);

    /* المكتب غيرُ مربوط: الإرسالُ يُردّ من المتحكّم برسالة عطل. قولُ
       ذلك قبل الكتابة أرحمُ من ردٍّ بعد أن كُتب. */
    $waConnected = (bool) data_get($snapshot ?? [], 'connected', false);

    /* الموكّل شرطُ ربطِ القضيّة عند المتحكّم — وقضايا القائمة تُجلب
       من قضاياه هو. بلا موكّلٍ مربوطٍ تعود القائمةُ فارغةً دائماً. */
    $waHasClient = $waContact?->client_id !== null;
@endphp

<div class="space-y-4"
     x-data="{ panel: false }">

    {{-- ══ الرجوع ══
         على الهاتف هذه هي الطريقة الوحيدة للعودة إلى القائمة: الخيط
         شاشةٌ كاملة لا لوحةٌ بجانبها. --}}
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('whatsapp.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gold-dark transition-colors md-touch">
            <svg class="w-4 h-4 {{ app()->getLocale() === 'ar' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            {{ __('app.wa_back_to_inbox') }}
        </a>

        {{-- زرُّ اللوحة على الهاتف فقط: على الشاشة الواسعة اللوحة ظاهرةٌ
             دائماً إلى جانب الخيط. --}}
        <button type="button" @click="panel = !panel"
                class="lg:hidden inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-600 md-touch">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/>
            </svg>
            {{ __('app.actions') }}
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

        {{-- ══════════ الخيط ══════════ --}}
        <div class="lg:col-span-8 bg-white rounded-xl border border-gray-200 overflow-hidden">

            {{-- ── ترويسة جهة الاتصال ── --}}
            <div class="px-4 sm:px-5 py-3.5 border-b border-gray-200">
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-11 h-11 rounded-xl bg-gold/12 border border-gold/20 text-gold-dark flex items-center justify-center font-bold" aria-hidden="true">
                        {{ $waInitial }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <h1 class="text-base sm:text-lg font-bold text-gray-900 truncate">{{ $waName }}</h1>
                        @if($waPhone !== '')
                            <p class="text-xs text-gray-500 mt-0.5" dir="ltr">{{ $waPhone }}</p>
                        @endif

                        <div class="mt-2 flex flex-wrap items-center gap-1.5">

                            @if($waContact?->client)
                                <a href="{{ route('clients.show', $waContact->client_id) }}"
                                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-700 border border-blue-200 hover:border-blue-300 transition-colors">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                    {{ $waContact->client->name }}
                                </a>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                    {{ __('app.wa_unlinked') }}
                                </span>
                            @endif

                            @if($conversation->case)
                                <a href="{{ route('cases.show', $conversation->case_id) }}"
                                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gold/12 text-gold-dark border border-gold/20 hover:border-gold/40 transition-colors">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    {{ $conversation->case->case_number ?: $conversation->case->title }}
                                </a>
                            @endif

                            @if($conversation->assignee)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                    {{ __('app.wa_assigned_to') }}: {{ $conversation->assignee->name }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                    {{ __('app.wa_unassigned') }}
                                </span>
                            @endif

                            @if($waClosed)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-500 border border-gray-200">
                                    {{ __('app.wa_filter_closed') }}
                                </span>
                            @endif

                            @if($waOpen)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold
                                             {{ $waMinsLeft <= 120 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-100 text-green-700 border border-green-200' }}">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{-- تحت الساعتين يُقال كم بقي بالدقائق —
                                         الرقمُ هو ما يجعل المحامي يردّ الآن.
                                         وفوقهما تكفي «مفتوحة»: عدٌّ من ألفِ
                                         دقيقة ضجيجٌ لا خبر. --}}
                                    {{ $waMinsLeft <= 120
                                        ? __('app.wa_window_left', ['minutes' => $waMinsLeft])
                                        : __('app.wa_window_open') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                    {{ __('app.wa_window_closed') }}
                                </span>
                            @endif

                            @unless($conversation->aiMayReply())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-purple-100 text-purple-700 border border-purple-200">
                                    {{ __('app.wa_ai_stopped') }}
                                </span>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── الرسائل ──
                 الأقدم أعلى والأحدث أسفل، والصندوق يُدفع إلى قاعه عند
                 الفتح: من يفتح خيطاً ليردّ يريد آخر ما قيل، لا أوّله
                 قبل ثلاثة أشهر. --}}
            <div class="px-3 sm:px-5 py-4 space-y-3 overflow-y-auto bg-gray-50"
                 style="height: clamp(20rem, 56vh, 40rem);"
                 x-data="{}"
                 x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">

                @forelse($messages as $message)
                    @include('whatsapp._message', ['message' => $message])
                @empty
                    <div class="h-full flex flex-col items-center justify-center text-center px-6">
                        <div class="w-12 h-12 mb-3 rounded-2xl bg-gold/10 border border-gold/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-bold text-gray-700">{{ __('app.wa_no_messages') }}</p>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ __('app.wa_no_messages_hint') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- ══════════ صندوق الكتابة ══════════ --}}
            <div class="border-t border-gray-200 bg-white"
                 x-data="{ tab: 'reply' }">

                {{-- تبويبان: ردٌّ يُرسل، وملاحظةٌ لا تُرسل. اللونُ وحده
                     لا يكفي لمن لا يميّز الألوان — فالوسمُ نصٌّ صريح
                     أيضاً، ولكلٍّ منهما نموذجٌ مستقلّ يقصد وجهته. --}}
                <div class="flex items-center gap-1 px-3 sm:px-4 pt-3">
                    <button type="button" @click="tab = 'reply'"
                            :aria-selected="tab === 'reply'"
                            class="md-tab px-3.5 py-2 rounded-t-lg text-xs font-bold transition-colors border-b-2"
                            :class="tab === 'reply'
                                ? 'text-gold-dark border-gold'
                                : 'text-gray-400 border-transparent hover:text-gray-600'">
                        {{ __('app.wa_tab_reply') }}
                    </button>
                    <button type="button" @click="tab = 'note'"
                            :aria-selected="tab === 'note'"
                            class="md-tab px-3.5 py-2 rounded-t-lg text-xs font-bold transition-colors border-b-2"
                            :class="tab === 'note'
                                ? 'text-amber-800 border-amber-400'
                                : 'text-gray-400 border-transparent hover:text-gray-600'">
                        {{ __('app.wa_tab_note') }}
                    </button>
                </div>

                {{-- ─────────── ردّ ─────────── --}}
                <div x-show="tab === 'reply'" class="p-3 sm:p-4">
                    @if(!$canSend)
                        <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5">
                            {{ __('app.wa_no_send_permission') }}
                        </p>

                    @elseif(!$waConnected)
                        {{-- بلا رقمٍ مربوط لا إرسال. يُقال هنا لا بعد أن
                             يكتب المحامي ردَّه ويضغط فيُردّ. --}}
                        <p class="text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5">
                            {{ __('app.wa_not_connected_readonly') }}
                        </p>

                    @elseif($waOpen)

                        {{-- النافذة مفتوحة: نصٌّ حرّ --}}
                        <form method="POST" action="{{ route('whatsapp.send', $conversation) }}"
                              x-data="{ len: 0 }">
                            @csrf
                            <div class="flex items-end gap-2">
                                {{-- ‏maxlength = ٤٠٠٠: هو نفسُ حدّ التحقّق في
                                     المتحكّم. بدونه يكتب المحامي مذكّرةً
                                     كاملة ثم تُردّ عليه بعد أن ظنّ أنّها
                                     ذهبت — والقصُّ عند الكتابة أرحم. --}}
                                <textarea name="body" rows="2" required maxlength="4000"
                                          @input="len = $el.value.length"
                                          placeholder="{{ __('app.wa_reply_placeholder') }}"
                                          aria-label="{{ __('app.wa_tab_reply') }}"
                                          class="form-input flex-1 min-w-0 rounded-xl bg-gray-50 border border-gray-200 px-3.5 py-2.5 text-sm text-gray-900 resize-y"
                                          style="max-height: 12rem;"></textarea>

                                <button type="submit"
                                        class="flex-shrink-0 bg-primary hover:bg-primary-dark text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm inline-flex items-center gap-1.5 md-touch">
                                    <svg class="w-4 h-4 {{ app()->getLocale() === 'ar' ? 'scale-x-[-1]' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                    </svg>
                                    {{ __('app.wa_send') }}
                                </button>
                            </div>
                            <p class="mt-1.5 text-[10px] text-gray-400" x-show="len > 3500" x-cloak>
                                <span x-text="len"></span> / 4000
                            </p>
                        </form>

                    @else

                        {{-- ══ النافذة مغلقة: قالبٌ معتمَد لا غير ══
                             تسمح Meta بالردّ الحرّ أربعاً وعشرين ساعةً من
                             آخر رسالةٍ للعميل. خارجها تُرفض الرسالة بالخطأ
                             ‏131047، فيرى المحامي «أُرسلت» ولم تصل أحداً.
                             الشرحُ هنا قبل الحقل لا بعد الفشل. --}}
                        <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5 mb-3">
                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                            </svg>
                            <p class="text-xs text-amber-800 leading-relaxed">{{ __('app.wa_window_hint') }}</p>
                        </div>

                        @if($waTemplates->isEmpty())
                            <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5">
                                {{ __('app.wa_template_none_approved') }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('whatsapp.send', $conversation) }}"
                                  x-data="{
                                      tpl: '',
                                      tpls: @js($waTplData),
                                      get current() { return this.tpls.find(t => t.name === this.tpl) || null; },
                                      get vars() { return this.current ? this.current.vars : 0; }
                                  }"
                                  class="space-y-3">
                                @csrf

                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1.5" for="waTemplate">{{ __('app.wa_template_pick') }}</label>
                                    <select id="waTemplate" name="template" x-model="tpl" required
                                            class="form-input w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2.5 text-sm">
                                        <option value="">{{ __('app.wa_template_none') }}</option>
                                        @foreach($waTemplates as $waTpl)
                                            <option value="{{ $waTpl->name }}">{{ $waTpl->name }} ({{ $waTpl->language }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- نصُّ القالب يُعرض قبل الإرسال: من لا يرى
                                     ما سيُرسل باسم المكتب يرسله على الظنّ.
                                     و‏x-text لا x-html — النصّ من Meta ولكنّه
                                     لا يُرسم وسوماً بحال. --}}
                                <template x-if="current">
                                    <p class="text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 whitespace-pre-wrap leading-relaxed"
                                       style="overflow-wrap: anywhere;" x-text="current.body"></p>
                                </template>

                                {{-- العنوان نصٌّ في العنصر لا داخل x-text:
                                     لو حُشرت الترجمة في سلسلةٍ جافاسكربتيّة
                                     لكسرها أوّلُ اقتباسٍ فيها وتعطّل الحقل
                                     كلّه بلا رسالة. --}}
                                <template x-for="i in vars" :key="i">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">
                                            {{ __('app.wa_template_variable') }} <span x-text="i"></span>
                                        </label>
                                        {{-- ‏params[] لا variables[]: هو الاسمُ
                                             الذي يقرأه المتحكّم. و‏required
                                             لأنّ القيم الفارغة تُصفّى هناك ثم
                                             يُقارَن العددُ بما اعتمدته Meta —
                                             فحقلٌ متروك يعني رفضاً بلا سبب
                                             ظاهر. و‏200 هو حدُّ التحقّق نفسه. --}}
                                        <input type="text" name="params[]" required maxlength="200"
                                               class="form-input w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm">
                                    </div>
                                </template>

                                <button type="submit" :disabled="!tpl"
                                        class="w-full sm:w-auto bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed md-touch">
                                    {{ __('app.wa_send') }}
                                </button>
                            </form>
                        @endif
                    @endif
                </div>

                {{-- ─────────── ملاحظة داخلية ─────────── --}}
                {{-- الملاحظةُ متاحةٌ دائماً — داخل النافذة وخارجها وبعد
                     إغلاق المحادثة: هي سجلٌّ للفريق لا رسالةٌ إلى أحد. --}}
                <div x-show="tab === 'note'" x-cloak class="p-3 sm:p-4">
                    <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5 mb-3">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/>
                        </svg>
                        <p class="text-xs font-semibold text-amber-800 leading-relaxed">{{ __('app.wa_note_never_sent') }}</p>
                    </div>

                    <form method="POST" action="{{ route('whatsapp.note', $conversation) }}">
                        @csrf
                        <div class="flex items-end gap-2">
                            <textarea name="body" rows="2" required maxlength="2000"
                                      placeholder="{{ __('app.wa_note_placeholder') }}"
                                      aria-label="{{ __('app.wa_tab_note') }}"
                                      class="form-input flex-1 min-w-0 rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-2.5 text-sm text-gray-900 resize-y"
                                      style="max-height: 12rem;"></textarea>

                            <button type="submit"
                                    class="flex-shrink-0 bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors text-sm md-touch">
                                {{ __('app.wa_save_note') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══════════ لوحة الإجراءات ══════════
             على الشاشة الواسعة عمودٌ ثابت إلى جانب الخيط؛ على الهاتف
             تُطوى ولا تُفتح إلا بالطلب، فلا تدفع الخيطَ خارج الشاشة. --}}
        {{-- الإخفاء بالأصناف لا بـx-show: «hidden lg:block» يعمل قبل أن
             يستيقظ Alpine، فلا تومض اللوحةُ كاملةً على الهاتف في كل
             فتحةِ خيط ثم تنطوي أمام العين. --}}
        <aside class="lg:col-span-4 space-y-4 hidden lg:block"
               :class="{ 'block': panel, 'hidden': !panel }">

            @if(!$canManage && !$canSend)
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs text-gray-500">{{ __('app.wa_no_manage_permission') }}</p>
                </div>
            @endif

            @if($canManage)

                {{-- ── الربط ── --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">
                    <h2 class="text-sm font-bold text-gray-800">{{ __('app.wa_links_title') }}</h2>

                    {{-- ══ ربطُ الموكّل ══
                         الرقمُ وحده لا يقول لمن هو. والربطُ هنا هو ما يجعل
                         الاسمَ يظهر في القائمة بدل رقمٍ مجرّد، وما يفتح
                         مستنداتِ المحادثة على ملفّه. --}}
                    <form method="POST" action="{{ route('whatsapp.link-client', $conversation) }}" class="space-y-2">
                        @csrf
                        <label class="block text-xs font-semibold text-gray-600" for="waClient">{{ __('app.wa_link_client') }}</label>
                        <select id="waClient" data-no-create name="client_id"
                                class="ts form-input w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm">
                            <option value="">{{ __('app.wa_unlinked') }}</option>
                            @foreach($clients ?? [] as $client)
                                <option value="{{ $client->id }}" {{ (int) $waContact?->client_id === (int) $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="w-full bg-gray-100 hover:bg-gold/12 border border-gray-200 text-gray-700 hover:text-gold-dark px-3 py-2 rounded-lg font-semibold text-xs transition-colors md-touch">
                            {{ __('app.save') }}
                        </button>
                    </form>

                    {{-- ══ ربطُ القضيّة ══
                         بدونه لا مكانَ يُحفظ فيه مرفقٌ وصل في المحادثة —
                         ويبقى المستند في واتساب حتى تنتهي صلاحيّته عند
                         ‏Meta ويضيع. --}}
                    @if(!$waHasClient)
                        {{-- بلا موكّلٍ مربوط تعود قائمةُ القضايا فارغةً
                             دائماً، ويردّ المتحكّم كلَّ محاولةِ ربط. حقلٌ
                             فارغ بلا تفسير يجعل الموظّف يظنّ أنّ للمكتب
                             لا قضايا — فيُقال السببُ والترتيب. --}}
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-1.5">{{ __('app.wa_link_case') }}</p>
                            <p class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 leading-relaxed">
                                {{ __('app.wa_link_client_first') }}
                            </p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('whatsapp.link-case', $conversation) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-semibold text-gray-600" for="waCase">{{ __('app.wa_link_case') }}</label>
                            <select id="waCase" data-no-create name="case_id"
                                    class="ts form-input w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm">
                                <option value="">{{ __('app.wa_unlinked') }}</option>
                                @foreach($cases ?? [] as $case)
                                    <option value="{{ $case->id }}" {{ (int) $conversation->case_id === (int) $case->id ? 'selected' : '' }}>
                                        {{ $case->case_number ? $case->case_number . ' — ' : '' }}{{ $case->title }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full bg-gray-100 hover:bg-gold/12 border border-gray-200 text-gray-700 hover:text-gold-dark px-3 py-2 rounded-lg font-semibold text-xs transition-colors md-touch">
                                {{ __('app.save') }}
                            </button>
                        </form>
                    @endif
                </div>

                {{-- ── الإسناد ── --}}
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                    <h2 class="text-sm font-bold text-gray-800">{{ __('app.wa_assign') }}</h2>

                    @if($waStaff->isNotEmpty())
                        <form method="POST" action="{{ route('whatsapp.assign', $conversation) }}" class="space-y-2">
                            @csrf
                            <select data-no-create name="assigned_to" aria-label="{{ __('app.wa_assign') }}"
                                    class="ts form-input w-full rounded-lg bg-white border border-gray-200 text-gray-900 px-3 py-2 text-sm">
                                <option value="">{{ __('app.wa_unassigned') }}</option>
                                @foreach($waStaff as $user)
                                    <option value="{{ $user->id }}" {{ (int) $conversation->assigned_to === (int) $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="w-full bg-gray-100 hover:bg-gold/12 border border-gray-200 text-gray-700 hover:text-gold-dark px-3 py-2 rounded-lg font-semibold text-xs transition-colors md-touch">
                                {{ __('app.save') }}
                            </button>
                        </form>
                    @endif

                    {{-- «أسنِدها إليّ» لا يحتاج قائمةَ موظّفين: من يفتح
                         محادثةً ويبدأ الردّ عليها يريد أن يعرف الفريقُ
                         أنّه أخذها، بضغطةٍ واحدة. --}}
                    @if((int) $conversation->assigned_to !== (int) ($me?->id))
                        <form method="POST" action="{{ route('whatsapp.assign', $conversation) }}">
                            @csrf
                            <input type="hidden" name="assigned_to" value="{{ $me?->id }}">
                            <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white px-3 py-2 rounded-lg font-semibold text-xs transition-colors md-touch">
                                {{ __('app.wa_assign_to_me') }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- ── الذكاء الاصطناعي والإغلاق ──
                 التحويلُ مفتوحٌ لمن يردّ (يشمل الموظّف)، والإغلاقُ للإدارة
                 وحدها — كما في حرّاس المسارين تماماً. ولو جُمعا تحت شرطٍ
                 واحد لحُرم الموظّفُ من إيقاف ردٍّ آليٍّ يراه يخطئ أمامه. --}}
            @if($canSend || $canManage)
                <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
                    <h2 class="text-sm font-bold text-gray-800">{{ __('app.actions') }}</h2>

                    {{-- ══ التحويل إلى موظّف ══
                         يوقف ردَّ الذكاء الاصطناعي في هذا الخيط وحده.
                         ولا رجعةَ عنه بضغطة: سؤالُ تأكيدٍ قبله، لأنّ من
                         حوّل محادثةً حسّاسة إلى إنسانٍ لا يريد أن يعود
                         الآلي إليها بغلطةِ إصبع. --}}
                    @if(!$canSend)
                        {{-- لا يردّ فلا يحوّل: يُقال حالُ الردّ الآلي بلا زرّ --}}
                        <p class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 leading-relaxed">
                            {{ $conversation->aiMayReply() ? __('app.wa_ai_active') : __('app.wa_handoff_done') }}
                        </p>
                    @elseif($conversation->aiMayReply())
                        <form method="POST" action="{{ route('whatsapp.handoff', $conversation) }}"
                              data-confirm="{{ __('app.wa_handoff_confirm') }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 bg-purple-100 hover:bg-purple-200 text-purple-700 border border-purple-200 px-3 py-2.5 rounded-lg font-semibold text-xs transition-colors md-touch">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                </svg>
                                {{ __('app.wa_handoff') }}
                            </button>
                        </form>
                        <p class="text-[11px] text-gray-400 leading-relaxed">{{ __('app.wa_handoff_hint') }}</p>
                    @else
                        <p class="text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 leading-relaxed">
                            {{ __('app.wa_handoff_done') }}
                            @if($conversation->handoff_at)
                                <span class="text-gray-400" dir="ltr">· {{ $conversation->handoff_at->format('Y-m-d H:i') }}</span>
                            @endif
                        </p>
                    @endif

                    {{-- المسارُ نفسه يفتح ويغلق — يقلب الحالةَ لا يثبّتها.
                         فلو بقي الزرّ «إغلاق» على محادثةٍ مغلقة لأعاد
                         فتحَها من ظنّ أنّه يؤكّد الإغلاق. والتسميةُ تتبع
                         الحالة، والتأكيدُ كذلك. --}}
                    @if($canManage)
                    <form method="POST" action="{{ route('whatsapp.close', $conversation) }}"
                          data-confirm="{{ $waClosed ? __('app.wa_reopen_confirm') : __('app.wa_close_confirm') }}">
                        @csrf
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-200 px-3 py-2.5 rounded-lg font-semibold text-xs transition-colors md-touch">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                @if($waClosed)
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                @endif
                            </svg>
                            {{ $waClosed ? __('app.wa_reopen') : __('app.wa_close') }}
                        </button>
                    </form>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection
