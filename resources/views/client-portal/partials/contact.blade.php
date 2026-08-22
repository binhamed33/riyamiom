@php $contact = \App\Support\ClientPortal::contact(); @endphp
@if (!empty($contact['email']) || !empty($contact['phone']))
    <section class="p-card p-in p-in-3" style="padding:1.3rem 1.4rem;margin-top:1.2rem">
        <h2 class="p-h2">{{ __('portal.contact.title') }}</h2>
        <p class="p-lede" style="margin-bottom:1rem">{{ __('portal.contact.lede') }}</p>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem">
            @if (!empty($contact['phone']))
                <a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}" class="p-btn p-btn-ghost">{{ __('portal.contact.call') }}</a>
            @endif
            @if (!empty($contact['email']))
                <a href="mailto:{{ $contact['email'] }}" class="p-btn p-btn-ghost">{{ __('portal.contact.email') }}</a>
            @endif
        </div>
    </section>
@endif
