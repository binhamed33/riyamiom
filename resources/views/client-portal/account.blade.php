@extends('client-portal.layout')
@section('title', __('portal.account.title'))

@php
    // الإخفاء يقع في الخادم: القالب لا يستلم الرقم كاملاً أصلاً
    $maskTail = function (?string $value, int $keep = 4): ?string {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if ($digits === '') return null;
        if (strlen($digits) <= $keep) return str_repeat('•', 3) . $digits;
        return str_repeat('•', min(7, strlen($digits) - $keep)) . substr($digits, -$keep);
    };
    $firstPhone = trim(explode(',', (string) $client->phone)[0] ?? '');
@endphp

@section('content')
<div class="p-in" style="margin-bottom:1.2rem">
    <h1 class="p-h1">{{ __('portal.account.title') }}</h1>
    <p class="p-lede">{{ __('portal.account.privacy_note') }}</p>
</div>

<section class="p-card p-in p-in-1" style="padding:1.4rem">
    <dl style="display:grid;gap:1.1rem;margin:0">
        <div>
            <dt style="font-size:.68rem;color:var(--fg-3);font-weight:600">{{ __('portal.account.name') }}</dt>
            <dd style="margin:.2rem 0 0;font-size:1rem;font-weight:700">{{ $client->name }}</dd>
        </div>

        @if ($masked = $maskTail($client->national_id))
            <div>
                <dt style="font-size:.68rem;color:var(--fg-3);font-weight:600">{{ __('portal.account.national_id') }}</dt>
                <dd style="margin:.2rem 0 0;font-size:.95rem;font-weight:600" dir="ltr">{{ $masked }}</dd>
            </div>
        @endif

        @if ($maskedPhone = $maskTail($firstPhone, 3))
            <div>
                <dt style="font-size:.68rem;color:var(--fg-3);font-weight:600">{{ __('portal.account.phone') }}</dt>
                <dd style="margin:.2rem 0 0;font-size:.95rem;font-weight:600" dir="ltr">{{ $maskedPhone }}</dd>
            </div>
        @endif
    </dl>
</section>

<form method="POST" action="{{ route('client.access.logout') }}" style="margin-top:1.2rem">
    @csrf
    <button class="p-btn p-btn-ghost" style="width:100%">{{ __('portal.nav.logout') }}</button>
</form>

@include('client-portal.partials.contact')
@endsection
