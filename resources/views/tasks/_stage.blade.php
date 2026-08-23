{{-- مسار المهمة.

     كان تغيير الحالة زرّين أخضرين متطابقين، لا يقول شكلُهما أين
     المهمة الآن ولا إلى أين تمضي. وكان كلٌّ منهما يمرّ عبر
     tasks.update فيرسل عنوان المهمة ووصفها ومَن أُسندت إليه كما
     كانت الصفحة ساعة فُتحت — فيمحو تعديل زميلٍ عدّلها في الأثناء.

     هنا: مسار من ثلاث محطّات يُرى فيه الماضي والحاضر والقادم،
     وفعلٌ واحد ظاهر هو الخطوة التالية، ورجوعٌ هادئ لمن أخطأ
     الضغط. وكل زرّ يخاطب tasks.status فلا يُكتب غير الحالة. --}}

@php
    $stages = [
        'pending'     => ['label' => __('app.status_pending'),     'hint' => __('app.task_stage_hint_pending')],
        'in_progress' => ['label' => __('app.status_in_progress'), 'hint' => __('app.task_stage_hint_in_progress')],
        'completed'   => ['label' => __('app.status_completed'),   'hint' => __('app.task_stage_hint_completed')],
    ];

    $order   = array_keys($stages);
    $current = array_search($task->status, $order, true);
    $current = $current === false ? 0 : $current;

    $next = $order[$current + 1] ?? null;
    $prev = $current > 0 ? $order[$current - 1] : null;
@endphp

@once
@push('styles')
<style>
    /* ===== مسار المهمة ===== */
    .md-stage-track { display: flex; align-items: flex-start; gap: 0; margin: 0; padding: 0; list-style: none; }
    .md-stage-node { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
        position: relative; text-align: center; min-width: 0; }

    /* الخيط الواصل يُرسم من العقدة إلى ما قبلها — والاتجاه يتبع الصفحة */
    .md-stage-node::before { content: ''; position: absolute; top: 15px; height: 2px;
        inset-inline-end: 50%; inset-inline-start: -50%; background: var(--md-stage-line, #E5E7EB); }
    .md-stage-node:first-child::before { display: none; }
    .md-stage-node.is-done::before, .md-stage-node.is-now::before { background: var(--accent-dark); }

    .md-stage-dot { position: relative; z-index: 1; width: 32px; height: 32px; border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.8rem; font-weight: 700; font-variant-numeric: tabular-nums;
        background: #FFFFFF; color: #9AA1AC; border: 2px solid var(--md-stage-line, #E5E7EB);
        transition: background 0.25s, border-color 0.25s, color 0.25s, box-shadow 0.25s; }
    .md-stage-label { font-size: 0.78rem; font-weight: 600; color: #6B7280; line-height: 1.5; }

    .md-stage-node.is-done .md-stage-dot { background: var(--accent-dark); border-color: var(--accent-dark); color: #FFFFFF; }
    .md-stage-node.is-done .md-stage-label { color: #6B7280; }

    .md-stage-node.is-now .md-stage-dot { background: #FFFFFF; border-color: var(--accent-dark); color: var(--accent-dark);
        box-shadow: 0 0 0 4px var(--accent-a12); }
    .md-stage-node.is-now .md-stage-label { color: var(--accent-dark); font-weight: 700; }

    .md-stage-hint { margin-top: 1rem; font-size: 0.82rem; color: #6B7280; text-align: center; }

    .md-stage-actions { margin-top: 1.1rem; display: flex; flex-direction: column; align-items: center; gap: 0.6rem; }
    .md-stage-go { display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.7rem 1.6rem; border-radius: 12px; font-size: 0.86rem; font-weight: 700;
        background: var(--accent-dark); color: #FFFFFF; border: 1px solid var(--accent-dark);
        transition: filter 0.2s, transform 0.08s; }
    .md-stage-go:hover { filter: brightness(1.08); }
    .md-stage-go:active { transform: translateY(1px); }
    .md-stage-go:focus-visible { outline: 2px solid var(--accent-dark); outline-offset: 3px; }
    .md-stage-go .md-stage-arrow { transition: transform 0.2s; }
    .md-stage-go:hover .md-stage-arrow { transform: translateX(var(--md-stage-nudge, -3px)); }
    [dir="ltr"] .md-stage-go { --md-stage-nudge: 3px; }

    .md-stage-back { font-size: 0.78rem; font-weight: 600; color: #8A9099;
        border-bottom: 1px dashed transparent; transition: color 0.2s, border-color 0.2s; }
    .md-stage-back:hover { color: var(--accent-dark); border-bottom-color: var(--accent-a30); }
    .md-stage-back:focus-visible { outline: 2px solid var(--accent-dark); outline-offset: 3px; border-radius: 4px; }

    [data-theme="dark"] .md-stage-track { --md-stage-line: #2A3242; }
    [data-theme="dark"] .md-stage-dot { background: #121826; color: #7C8494; }
    [data-theme="dark"] .md-stage-node.is-now .md-stage-dot { background: #121826; }
    [data-theme="dark"] .md-stage-label,
    [data-theme="dark"] .md-stage-hint { color: #98A0AE; }

    @media (max-width: 420px) {
        .md-stage-label { font-size: 0.7rem; }
        .md-stage-go { width: 100%; justify-content: center; }
    }
</style>
@endpush
@endonce

<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-5">{{ __('app.change_status') }}</h3>

    <ol class="md-stage-track">
        @foreach ($stages as $key => $stage)
            @php $i = array_search($key, $order, true); @endphp
            <li class="md-stage-node {{ $i < $current ? 'is-done' : ($i === $current ? 'is-now' : '') }}"
                @if ($i === $current) aria-current="step" @endif>
                <span class="md-stage-dot" aria-hidden="true">
                    @if ($i < $current || ($i === $current && ! $next))
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>
                <span class="md-stage-label">{{ $stage['label'] }}</span>
            </li>
        @endforeach
    </ol>

    <p class="md-stage-hint">
        {{ $stages[$order[$current]]['hint'] }}
        @if ($task->status === 'completed' && $task->completed_at)
            <span class="block mt-1 text-xs opacity-80" dir="ltr">
                {{ __('app.task_completed_on', ['date' => $task->completed_at->format('Y-m-d')]) }}
            </span>
        @endif
    </p>

    <div class="md-stage-actions">
        @if ($next)
            <form method="POST" action="{{ route('tasks.status', $task) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $next }}">
                <button type="submit" class="md-stage-go">
                    {{ __('app.task_action_' . $next) }}
                    <svg class="w-4 h-4 md-stage-arrow" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="{{ app()->getLocale() === 'ar' ? 'M11 17l-5-5 5-5m7 10l-5-5 5-5' : 'M13 7l5 5-5 5M6 7l5 5-5 5' }}"/>
                    </svg>
                </button>
            </form>
        @endif

        @if ($prev)
            <form method="POST" action="{{ route('tasks.status', $task) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $prev }}">
                <button type="submit" class="md-stage-back">
                    {{ $task->status === 'completed'
                        ? __('app.task_reopen')
                        : __('app.task_step_back', ['status' => $stages[$prev]['label']]) }}
                </button>
            </form>
        @endif
    </div>
</div>
