<?php

namespace App\Http\Controllers;

use App\Models\LegalCase;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        @set_time_limit(180);

        $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $service = new GeminiService();

        if (!$service->isConfigured()) {
            return response()->json([
                'error' => 'لم يتم إعداد مفتاح Gemini في ملف الإعدادات، يرجى التواصل مع المطور',
            ], 400);
        }

        $userMessage = trim($request->input('message'));

        $history = session('assistant_history', []);
        $history[] = ['role' => 'user', 'content' => $userMessage];
        $history = collect($history)->take(-20)->values()->toArray();

        $systemPrompt = $this->buildSystemPrompt();

        try {
            $reply = $service->chat($history, $systemPrompt);

            if (!$reply) {
                return response()->json([
                    'error' => 'تعذر الحصول على رد من الذكاء الاصطناعي، حاول مرة أخرى لاحقاً',
                ], 500);
            }

            $history[] = ['role' => 'assistant', 'content' => $reply];
            session(['assistant_history' => collect($history)->take(-20)->values()->toArray()]);

            return response()->json([
                'reply' => $reply,
            ]);
        } catch (\Throwable $e) {
            Log::error('Assistant chat failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'خطأ من خدمة الذكاء الاصطناعي: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function history(): JsonResponse
    {
        $messages = collect(session('assistant_history', []))
            ->map(fn($m) => ['id' => uniqid(), 'role' => $m['role'], 'content' => $m['content']])
            ->values()
            ->toArray();

        return response()->json(['messages' => $messages]);
    }

    public function clear(): JsonResponse
    {
        session()->forget('assistant_history');
        return response()->json(['ok' => true]);
    }

    protected function buildSystemPrompt(): string
    {
        $cases = LegalCase::with('client')
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        $casesText = $cases->map(function ($c) {
            return "- قضية رقم {$c->office_case_number} ({$c->case_type}): {$c->title} — المحكمة: {$c->court} — الحالة: {$c->status} — الموكل: {$c->client?->name}";
        })->join("\n") ?: '- لا توجد قضايا مسجلة حالياً';

        return <<<SYSTEM
أنت مساعد قانوني ذكي مدمج في نظام إدارة مكتب محاماة عماني، متاح لجميع موظفي المكتب. أنت محامٍ خبير في القوانين السارية في سلطنة عمان (قانون المعاملات المدنية، قانون الإجراءات المدنية والتجارية، قانون الإثبات، قانون العمل، قانون الشركات التجارية، قانون التجارة، قانون الجزاء، قانون الإجراءات الجزائية، قانون المرافعات الشرعية، قوانين التنفيذ، نظام المحاماة، وأحكام المحكمة العليا العمانية).

القضايا الحالية في المكتب (يمكنك الاستفادة منها عند الإجابة):
{$casesText}

قواعد صارمة يجب الالتزام بها:
- أجب باللغة العربية الفصحى دائماً.
- لا تجيب إطلاقاً عن أي سؤال خارج القانون أو خارج نطاق العمل القانوني (لا سياسة، لا رياضة، لا أخبار عامة، لا برمجة، لا نصائح طبية أو مالية شخصية، لا شؤون عامة غير قانونية). إذا كان السؤال خارج نطاق القانون العماني أو العمل القانوني، اعتذر بلطف واذكر أنك مخصص فقط للأسئلة القانونية والمتعلقة بقضايا المكتب، ثم وجّه صاحب السؤال للموارد المناسبة.
- الأسئلة القانونية العامة (مدني، عمالي، تجاري، جزائي، أحوال شخصية، إجراءات، إثبات، تنفيذ) مرحب بها دائماً.
- استند في إجاباتك إلى القانون العماني فقط، واذكر القانون أو المبدأ ذي الصلة.
- لا تختلق نصوص مواد قانونية؛ إذا لم تكن متأكداً من رقم المادة، اشرح المبدأ القانوني والمرجع العام ونبّه للتحقق من النص الرسمي.
- يمكنك الإشارة إلى قضايا المكتب المذكورة أعلاه عند السؤال عنها (مثل: حالة القضية، نوعها، المحكمة)، لكن لا تقدم معلومات تفصيلية غير موجودة في هذه القائمة، ولا تكشف معلومات سرية خارج المطلوب.
- أجب بإجابات عملية ومركزة ومختصرة قدر الإمكان.
- استخدم عناوين أو نقاط عند الحاجة لتسهيل القراءة.
SYSTEM;
    }
}
