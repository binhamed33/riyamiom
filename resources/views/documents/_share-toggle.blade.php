{{--
    زرُّ مشاركة المستند مع الموكّل وإخفائه عنه.

    كان المسارُ documents.clientVisibility موجوداً ولا زرَّ يصل إليه في أيّ
    شاشة، فبقيت «المستندات» في بوابة الموكّل فارغةً إلا ممّا زُرع. والزرُّ
    يظهر بشروط المتحكّم نفسِها: الخاصُّ لا يُشارَك أبداً، ومستندُ «الفريق»
    لا يُتيحه إلا المحامي فمن فوقه — زرٌّ يردّ ٤٠٣ أسوأ من غيابه. والشرطُ
    من Document::canBeSharedBy لا نسخةٌ منه هنا: كانت النسخةُ والرفعُ
    يختلفان، فيُتيح الموظّفُ بالرفع ما لا زرَّ له يتراجع به.

    $size: ‏'sm' للمصغّرات (٧) و'md' للجدول وصفحة القضية (٨).
--}}
@php
    $__canShare = \App\Models\Document::canBeSharedBy(auth()->user(), $document->access_level);
    $__shared = (bool) $document->client_visible;
    $__box = ($size ?? 'md') === 'sm' ? 'w-7 h-7' : 'w-8 h-8';
    $__icon = ($size ?? 'md') === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4';
@endphp
@if($__canShare)
    <form method="POST" action="{{ route('documents.clientVisibility', $document) }}" class="contents" data-document-share>
        @csrf
        <button type="submit"
                class="inline-flex items-center justify-center {{ $__box }} rounded-lg transition-colors {{ $__shared ? 'bg-blue-500/15 text-blue-600 hover:bg-blue-500/25' : 'bg-gray-500/10 text-gray-400 hover:bg-gray-500/20 hover:text-gray-600' }}"
                title="{{ $__shared ? __('app.document_unshare_client') : __('app.document_share_client') }}"
                aria-label="{{ $__shared ? __('app.document_unshare_client') : __('app.document_share_client') }}"
                aria-pressed="{{ $__shared ? 'true' : 'false' }}">
            @if($__shared)
                {{-- عينٌ مفتوحة: يراه الموكّل الآن --}}
                <svg class="{{ $__icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            @else
                <svg class="{{ $__icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/></svg>
            @endif
        </button>
    </form>
@endif
