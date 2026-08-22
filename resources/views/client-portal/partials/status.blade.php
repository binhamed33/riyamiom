@php
    $tone = match ($status) {
        \App\Models\LegalCase::STATUS_ACTIVE, \App\Models\LegalCase::STATUS_WON => 'ok',
        \App\Models\LegalCase::STATUS_PENDING, \App\Models\LegalCase::STATUS_FEES_PENDING => 'info',
        \App\Models\LegalCase::STATUS_OVERDUE => 'warn',
        default => 'mute',
    };
    $key = 'portal.status.' . $status;
    $label = __($key);
@endphp
<span class="p-badge {{ $tone }}">{{ $label === $key ? $status : $label }}</span>
