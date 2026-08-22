@extends('client-portal.layout')
@section('title', __('portal.portal'))

@section('content')
<div class="p-card p-in" style="max-width:520px;margin:3rem auto">
    <div class="p-empty">
        <span class="p-empty-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM16 11V7a4 4 0 00-8 0v4"/></svg>
        </span>
        <p>{{ __('portal.login.disabled') }}</p>
        <small>{{ __('portal.login.disabled_hint') }}</small>
    </div>
    @include('client-portal.partials.contact')
</div>
@endsection
