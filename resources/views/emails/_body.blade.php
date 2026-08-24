{{--
    متنُ الرسالة: نصٌّ عاديّ يُحوَّل إلى فقرات.

    النصُّ يأتي من ClientMessage بصيغةٍ بسيطة فيها **تعليم عريض**
    وأسطرٌ فارغة. ويُهرَّب أولاً ثم يُعلَّم: عكسُ الترتيب يجعل اسم
    موكّلٍ فيه قوسٌ زاويّ يكسر الرسالة.
--}}
@php
    $paragraphs = preg_split('/\n{2,}/', trim((string) ($body ?? '')));
@endphp
@foreach($paragraphs as $paragraph)
    @php
        $safe = e(trim($paragraph));
        $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong style="color:#1b1c20;">$1</strong>', $safe);
        $safe = nl2br($safe);
    @endphp
    <p style="margin:0 0 14px;">{!! $safe !!}</p>
@endforeach
