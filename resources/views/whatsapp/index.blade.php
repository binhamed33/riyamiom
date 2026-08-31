@extends('layouts.app')

@section('title', __('app.wa_inbox_title'))

@section('content')
@php
    /* ══ الصلاحيات تُحسب مرّة واحدة لا مرّةً لكل صفّ ══
       hasPermission() يضرب قاعدة البيانات في كل نداء، وقائمة المحادثات
       تبلغ خمسين صفّاً في الصفحة. حسابُها داخل الحلقة كان يعني خمسين
       استعلاماً لسؤالٍ جوابُه واحد لا يتغيّر. */
    $me = auth()->user();
    /* رابطُ الإعدادات مرآةٌ لحارس settings.index: المطوّر أو مدير
       المكتب أو من معه settings.manage. وعرضُه لغيرهم يقودهم إلى 403. */
    $canSettings = $me && ($me->isDeveloper() || $me->isAdmin() || $me->hasPermission('settings.manage'));

    /* ══ المرشِّح النشط والبحث ══
       المتحكّم يمرّرهما مطبَّعَين ($filter و$search)، والرجوعُ إلى
       الرابط حارسٌ لا غير. واسمُ حقل البحث في الرابط «q» — هو ما
       يقرأه المتحكّم، وأيُّ اسمٍ سواه يجعل البحثَ يبتلع نفسه بصمت. */
    $waFilter = (string) ($filter ?? request()->query('filter', 'all'));
    $waSearch = (string) ($search ?? request()->query('q', ''));

    $waChips = [
        'all'        => ['label' => __('app.wa_filter_all'),        'count' => (int) data_get($counts ?? [], 'all', 0)],
        'unread'     => ['label' => __('app.wa_filter_unread'),     'count' => (int) data_get($counts ?? [], 'unread', 0)],
        'mine'       => ['label' => __('app.wa_filter_mine'),       'count' => (int) data_get($counts ?? [], 'mine', 0)],
        'unassigned' => ['label' => __('app.wa_filter_unassigned'), 'count' => (int) data_get($counts ?? [], 'unassigned', 0)],
    ];

    $waConnected = (bool) data_get($snapshot ?? [], 'connected', false);
    $waAttention = (bool) data_get($snapshot ?? [], 'needs_attention', false);
    $waHasRows   = isset($conversations) && $conversations->count() > 0;
    $waFiltered  = $waSearch !== '' || ($waFilter !== '' && $waFilter !== 'all');
@endphp

<div class="space-y-5">

    {{-- ══ الترويسة ══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gold-dark">{{ __('app.wa_inbox_title') }}</h1>

            {{-- الأخضر هنا نقطةُ حالةٍ لا لونُ واجهة: هو العُرف الذي
                 يقرأه الجميع بلا نصّ، ولا يُقحم علامة تطبيقٍ آخر على
                 هوية المكتب. --}}
            @if($waConnected)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-600" aria-hidden="true"></span>
                    {{ __('app.wa_connected') }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-3 min-w-0">
            @if($waConnected && data_get($snapshot, 'display_phone'))
                <span class="text-sm text-gray-500 truncate" dir="ltr">{{ data_get($snapshot, 'display_phone') }}</span>
            @endif
            @if($canSettings)
                <a href="{{ route('settings.index') }}#whatsapp"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gold-dark transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ __('app.wa_settings_link') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ══ ربطٌ يبدو حيّاً وهو ميت ══
         مربوطٌ ولم يصل إشعارٌ منذ مدّة: لا عطلَ ظاهر، والرسائل الواردة
         تضيع بصمت. هذا التنبيه هو الفرق بين اكتشافه اليوم واكتشافه بعد
         أسبوعٍ من شكاوى موكّلين لم يُردّ عليهم. --}}
    @if($waConnected && $waAttention)
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-amber-800">{{ __('app.wa_needs_attention') }}</p>
                @if(data_get($snapshot, 'error'))
                    <p class="text-xs text-amber-700 mt-1 break-words" style="overflow-wrap: anywhere;">{{ data_get($snapshot, 'error') }}</p>
                @endif
            </div>
            @if($canSettings)
                <a href="{{ route('settings.index') }}#whatsapp" class="text-xs font-bold text-amber-800 underline underline-offset-4 whitespace-nowrap">{{ __('app.wa_settings_link') }}</a>
            @endif
        </div>
    @endif

    @if(!$waConnected && $waHasRows)
        {{-- غيرُ مربوط ولكن في السجل محادثاتٌ سابقة: تُعرض للقراءة،
             ويُقال بهدوءٍ إنّ الإرسال متوقّف — لا رسالةَ عطلٍ حمراء
             على شاشةِ من لم يربط بعد. --}}
        <div class="flex items-start gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
            <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
            </svg>
            <p class="text-sm text-gray-600 flex-1 min-w-0">{{ __('app.wa_not_connected_readonly') }}</p>
            @if($canSettings)
                <a href="{{ route('settings.index') }}#whatsapp" class="text-xs font-bold text-gold-dark underline underline-offset-4 whitespace-nowrap">{{ __('app.wa_connect_now') }}</a>
            @endif
        </div>
    @endif

    @if(!$waConnected && !$waHasRows && !$waFiltered)

        {{-- ══ لم يُربط رقمٌ بعد ══
             هذه ليست حالة عطل بل حالة «لم يبدأ»: بطاقةٌ هادئة تشرح
             وتدلّ على الإعدادات، لا لونٌ أحمر ولا كلمة «خطأ». --}}
        <div class="bg-white rounded-xl border border-gray-200 px-6 py-14 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-gold/10 border border-gold/20 flex items-center justify-center">
                <svg class="w-7 h-7 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
            </div>
            <p class="text-base font-bold text-gray-800">{{ __('app.wa_not_connected_title') }}</p>
            <p class="text-sm text-gray-500 mt-1.5 max-w-md mx-auto leading-relaxed">{{ __('app.wa_not_connected_hint') }}</p>

            @if($canSettings)
                <a href="{{ route('settings.index') }}#whatsapp"
                   class="mt-5 inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">
                    {{ __('app.wa_connect_now') }}
                </a>
            @else
                {{-- الموظّف لا يملك الإعدادات: إخبارُه بمن يفعلها أنفعُ
                     من زرٍّ يقوده إلى صفحةٍ تردّه. --}}
                <p class="mt-5 text-xs text-gray-400">{{ __('app.wa_ask_admin_to_connect') }}</p>
            @endif
        </div>

    @else

        {{-- ══ البحث والمرشِّحات ══ --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
            <form method="GET" action="{{ route('whatsapp.index') }}" class="flex items-center gap-2">
                {{-- الشريحة النشطة تُحمل في حقلٍ خفيّ: لولاه لعاد البحث
                     بالمستخدم إلى «الكل» فيظنّ أنّ مرشِّحه ضاع. --}}
                <input type="hidden" name="filter" value="{{ $waFilter }}">

                <div class="relative flex-1 min-w-0">
                    <span class="absolute inset-y-0 flex items-center pointer-events-none {{ app()->getLocale() === 'ar' ? 'right-3' : 'left-3' }}">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </span>
                    <input type="search" name="q" value="{{ $waSearch }}"
                           placeholder="{{ __('app.wa_search_placeholder') }}"
                           aria-label="{{ __('app.wa_search_placeholder') }}"
                           class="form-input w-full rounded-lg bg-white border border-gray-200 text-gray-900 py-2.5 text-sm {{ app()->getLocale() === 'ar' ? 'pr-9 pl-3' : 'pl-9 pr-3' }}">
                </div>

                <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-5 py-2.5 rounded-lg font-semibold transition-colors text-sm whitespace-nowrap">
                    {{ __('app.search') }}
                </button>

                @if($waSearch !== '')
                    <a href="{{ route('whatsapp.index', $waFilter === 'all' ? [] : ['filter' => $waFilter]) }}"
                       class="text-sm text-gray-500 hover:text-gold-dark transition-colors whitespace-nowrap">
                        {{ __('app.clear_filters') }}
                    </a>
                @endif
            </form>

            {{-- الشرائح تُبقي البحثَ قائماً، والعكس كذلك: تبديلُ الشريحة
                 لا يمحو ما كتبه الباحث. --}}
            {{-- الشرائح تلتفّ ولا تُسحب أفقياً: الصفحةُ نفسها يجب ألّا
                 تُزحزح يميناً ويساراً على الهاتف. --}}
            <div class="flex flex-wrap items-center gap-2">
                @foreach($waChips as $key => $chip)
                    @php
                        $isOn = $waFilter === $key || ($key === 'all' && $waFilter === '');
                        $chipQuery = array_filter([
                            'filter' => $key === 'all' ? null : $key,
                            'q' => $waSearch !== '' ? $waSearch : null,
                        ]);
                    @endphp
                    <a href="{{ route('whatsapp.index', $chipQuery) }}"
                       @if($isOn) aria-current="page" @endif
                       class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold border transition-colors
                              {{ $isOn
                                    ? 'bg-gold/12 text-gold-dark border-gold/30'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-gold/30 hover:text-gold-dark' }}">
                        {{ $chip['label'] }}
                        @if($chip['count'] > 0)
                            <span class="inline-flex items-center justify-center min-w-[1.25rem] px-1 rounded-full text-[10px] font-bold {{ $isOn ? 'bg-gold/20 text-gold-dark' : 'bg-gray-100 text-gray-500' }}">
                                {{ $chip['count'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ══ لوحتان على الشاشة الواسعة، شاشةٌ واحدة على الهاتف ══
             القائمة وحدها على الجوّال؛ النقرُ ينقل إلى صفحة الخيط وفيها
             رابطُ رجوع. ولا يُرسم عمودُ التلميح على الجوّال أصلاً، فلا
             يزاحم القائمةَ في مساحةٍ ضيّقة. --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            <div class="lg:col-span-7 xl:col-span-8 bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="md-zebra">
                    @forelse($conversations as $conversation)
                        @php
                            /* ══ آخر رسالة ══
                               المتحكّم يجلبها لكل محادثات الصفحة باستعلامٍ
                               واحد ويسلّمها مفهرسةً بمعرّف المحادثة. قراءتُها
                               من هنا باستعلامٍ كانت تعني خمسةً وعشرين
                               استعلاماً في فتحةِ صفحةٍ واحدة. */
                            $waLast = ($lastMessages ?? collect())->get($conversation->id);

                            $waContact = $conversation->contact;
                            $waName = $waContact?->displayName() ?: __('app.wa_unknown_contact');
                            $waInitial = mb_substr(trim($waName), 0, 1) ?: '#';
                            $waUnread = (int) ($conversation->unread_count ?? 0);
                            $waMinsLeft = $conversation->windowOpen() ? $conversation->windowMinutesLeft() : 0;

                            /* رقمُ القضيّة واسمُ المُسنَد إليه زينةٌ في الشريحة،
                               والزينةُ لا تستحقّ استعلاماً لكل صفّ. تُقرأ إن
                               كانت العلاقةُ محمَّلة، وإلّا اكتفت الشريحةُ
                               بعنوانها العامّ — فالخبرُ (مربوطة/مُسنَدة)
                               مقروءٌ من المفتاح نفسه بلا استعلام. */
                            $waCaseNo = $conversation->relationLoaded('case')
                                ? $conversation->case?->case_number
                                : null;
                            $waAssignee = $conversation->relationLoaded('assignee')
                                ? $conversation->assignee?->name
                                : null;
                        @endphp

                        <a href="{{ route('whatsapp.show', $conversation) }}"
                           class="block px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start gap-3">

                                {{-- حرفُ الاسم بدل صورة الملف: صورةُ واتساب
                                     تُجلب من خوادم Meta بكل فتحة قائمة —
                                     تسريبُ حركةٍ خارجيّ لا يستحقّه حرفٌ. --}}
                                <span class="flex-shrink-0 w-10 h-10 rounded-xl bg-gold/12 border border-gold/20 text-gold-dark flex items-center justify-center font-bold text-sm" aria-hidden="true">
                                    {{ $waInitial }}
                                </span>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold text-gray-800 truncate {{ $waUnread > 0 ? 'text-gray-900' : '' }}">{{ $waName }}</p>

                                        @if($waUnread > 0)
                                            <span class="flex-shrink-0 inline-flex items-center justify-center min-w-[1.35rem] h-5 px-1.5 rounded-full bg-gold text-[#111827] text-[11px] font-bold"
                                                  aria-label="{{ __('app.wa_unread_aria') }}">
                                                {{ $waUnread > 99 ? '99+' : $waUnread }}
                                            </span>
                                        @endif

                                        <span class="ms-auto flex-shrink-0 text-[11px] text-gray-400 whitespace-nowrap">
                                            {{ $conversation->last_message_at?->diffForHumans(null, true) ?? '—' }}
                                        </span>
                                    </div>

                                    {{-- المعاينة نصٌّ هارب دائماً: جسمُ الرسالة
                                         يكتبه من في الطرف الآخر، ولو رُسم خاماً
                                         لصار وسمُ <script> في رسالةٍ واردة
                                         شيفرةً تعمل في متصفّح المحامي. --}}
                                    <p class="text-sm mt-0.5 truncate {{ $waUnread > 0 ? 'text-gray-700 font-medium' : 'text-gray-500' }}">
                                        @if($waLast)
                                            @if(!$waLast->isInbound() && !$waLast->is_internal)
                                                <span class="text-gray-400">{{ __('app.wa_preview_you') }}</span>
                                            @endif
                                            {{ $waLast->preview() }}
                                        @else
                                            <span class="text-gray-400">{{ __('app.wa_no_preview') }}</span>
                                        @endif
                                    </p>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">

                                        @if($waContact?->client_id)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-700 border border-blue-200"
                                                  title="{{ __('app.wa_linked_client') }}">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                                </svg>
                                                {{ __('app.wa_linked_client') }}
                                            </span>
                                        @endif

                                        @if($conversation->case_id)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gold/12 text-gold-dark border border-gold/20"
                                                  title="{{ __('app.wa_linked_case') }}">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                                </svg>
                                                {{ $waCaseNo ?: __('app.wa_linked_case') }}
                                            </span>
                                        @endif

                                        @if($conversation->assigned_to)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200"
                                                  title="{{ __('app.wa_assigned_to') }}">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                                </svg>
                                                {{ $waAssignee ?: __('app.wa_assigned_to') }}
                                            </span>
                                        @endif

                                        @if($conversation->status === \App\Models\WhatsAppConversation::STATUS_CLOSED)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                {{ __('app.wa_filter_closed') }}
                                            </span>
                                        @endif

                                        {{-- ══ تلميحُ النافذة ══
                                             خارج أربعٍ وعشرين ساعةً من آخر رسالةٍ
                                             للعميل لا يمرّ إلا قالبٌ معتمَد. من
                                             يرى «تُغلق بعد ساعة» يردّ الآن؛ ومن
                                             لا يراه يكتب ردّاً يُرفض عند Meta
                                             ويظنّه وصل. --}}
                                        @if($waMinsLeft > 0 && $waMinsLeft <= 120)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                {{ __('app.wa_window_left', ['minutes' => $waMinsLeft]) }}
                                            </span>
                                        @elseif($waMinsLeft === 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                                {{ __('app.wa_window_closed') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <x-empty-state
                            :title="__('app.wa_no_conversations')"
                            :hint="__('app.wa_no_conversations_hint')"
                            icon="inbox"
                            :filtered="$waFiltered"
                            :clear-url="route('whatsapp.index')" />
                    @endforelse
                </div>
            </div>

            {{-- ══ عمود التلميح — الشاشة الواسعة وحدها ══ --}}
            <aside class="hidden lg:block lg:col-span-5 xl:col-span-4 bg-white rounded-xl border border-gray-200 p-6 text-center sticky top-24">
                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-gold/10 border border-gold/20 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gold-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                    </svg>
                </div>
                <p class="text-sm font-bold text-gray-800">{{ __('app.wa_pick_conversation') }}</p>
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">{{ __('app.wa_pick_conversation_hint') }}</p>

                @if($waConnected)
                    <dl class="mt-5 space-y-2.5 text-start border-t border-gray-100 pt-4">
                        @if(data_get($snapshot, 'business_name'))
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[11px] text-gray-400">{{ __('app.wa_business_name') }}</dt>
                                <dd class="text-xs font-semibold text-gray-700 truncate">{{ data_get($snapshot, 'business_name') }}</dd>
                            </div>
                        @endif
                        @if(data_get($snapshot, 'display_phone'))
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[11px] text-gray-400">{{ __('app.wa_number') }}</dt>
                                <dd class="text-xs font-semibold text-gray-700 truncate" dir="ltr">{{ data_get($snapshot, 'display_phone') }}</dd>
                            </div>
                        @endif
                        @if(data_get($snapshot, 'last_webhook_at'))
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[11px] text-gray-400">{{ __('app.wa_last_webhook') }}</dt>
                                <dd class="text-xs font-semibold text-gray-700 truncate" dir="ltr">{{ data_get($snapshot, 'last_webhook_at') }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif
            </aside>
        </div>

        @if(method_exists($conversations, 'links'))
            <div>{{ $conversations->withQueryString()->links() }}</div>
        @endif

    @endif
</div>
@endsection
