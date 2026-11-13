@extends('client-portal.layout')
@section('title', 'اختر ملفّك')

@push('styles')
<style>
    .ch-wrap { max-width: 560px; margin: 2.2rem auto 0; }
    .ch-list { display: grid; gap: .7rem; margin-top: 1.4rem; }
    .ch-item {
        width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 1rem 1.15rem; border-radius: 16px; cursor: pointer; text-align: start;
        background: var(--surface-2); border: 1px solid var(--line); color: var(--fg);
        transition: border-color .18s, transform .18s, box-shadow .18s;
    }
    .ch-item:hover, .ch-item:focus-visible { outline: none; border-color: var(--gold); box-shadow: 0 0 0 4px var(--gold-soft); transform: translateY(-1px); }
    .ch-item.is-current { border-color: var(--gold); }
    .ch-name { font-weight: 700; font-size: .98rem; }
    .ch-meta { font-size: .74rem; color: var(--fg-3); margin-top: .2rem; }
    .ch-go { font-size: .74rem; font-weight: 700; color: var(--gold); white-space: nowrap; }
</style>
@endpush

@section('content')
<div class="ch-wrap">
    <section class="p-card p-in" style="padding:2rem 1.6rem">
        <h1 class="p-h1">اختر ملفّك</h1>
        {{-- الرقمُ ثبت بالرمز: هذه كلُّ الملفّات التي سجّلها المكتبُ عليه --}}
        <p class="p-lede">رقمُ هاتفك مسجَّلٌ لدى المكتب على أكثر من ملفّ. اختر الملفَّ الذي تريد متابعتَه — وتستطيع التبديلَ بينها لاحقاً.</p>

        <div class="ch-list">
            @foreach ($clients as $c)
                @php $cases = $c->cases()->count(); @endphp
                <form method="POST" action="{{ route('client.access.choose.store') }}">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $c->id }}">
                    <button type="submit" @class(['ch-item', 'is-current' => $current && $current->id === $c->id])>
                        <span>
                            <span class="ch-name">{{ $c->name }}</span>
                            <span class="ch-meta" style="display:block">
                                {{ $c->type === 'company' ? 'شركة' : 'فرد' }}
                                · {{ $cases === 0 ? 'لا قضايا' : ($cases === 1 ? 'قضيّةٌ واحدة' : $cases . ' قضايا') }}
                                · ملفّ رقم {{ $c->id }}
                            </span>
                        </span>
                        <span class="ch-go">{{ $current && $current->id === $c->id ? 'الحاليّ' : 'افتح ←' }}</span>
                    </button>
                </form>
            @endforeach
        </div>

        {{-- خروجٌ من هنا أيضاً: على هاتفٍ مشترك لا تُترك جلسةٌ تفتح كلَّ ملفّات الرقم --}}
        <form method="POST" action="{{ route('client.access.logout') }}" style="margin-top:1.1rem">
            @csrf
            <button class="p-btn p-btn-ghost" style="width:100%">{{ $current ? 'تسجيل الخروج' : 'إلغاء' }}</button>
        </form>
    </section>
</div>
@endsection
