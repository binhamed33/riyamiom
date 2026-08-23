<?php

namespace App\Http\Controllers;

use App\Models\AssistantMessage;
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
                'error' => \App\Support\AiSettings::notConfiguredMessage(),
            ], 400);
        }

        $userId = auth()->id();
        $userMessage = trim($request->input('message'));

        // السؤال يُحفظ قبل الطلب لا بعده: إن سقط الاتصال بقي سؤال
        // الموظّف مكتوباً أمامه بدل أن يضيع ويُعاد كتابته.
        $asked = AssistantMessage::write($userId, 'user', $userMessage);

        $history = AssistantMessage::contextFor($userId);
        $systemPrompt = $this->buildSystemPrompt();

        try {
            $reply = $service->chat($history, $systemPrompt);

            if (!$reply) {
                return response()->json([
                    'question_id' => $asked->id,
                    'error' => 'تعذر الحصول على رد من الذكاء الاصطناعي، حاول مرة أخرى لاحقاً',
                ], 500);
            }

            $answer = AssistantMessage::write($userId, 'assistant', $reply);

            return response()->json([
                'reply' => $reply,
                'question_id' => $asked->id,
                'id' => $answer->id,
                'at' => $answer->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Assistant chat failed: ' . $e->getMessage());

            return response()->json([
                'question_id' => $asked->id,
                'error' => $service->getLastError() ?: 'المساعد القانوني مزدحم حاليًا، حاول مرة أخرى بعد لحظات.',
            ], 503);
        }
    }

    public function history(): JsonResponse
    {
        // معرّفٌ ثابت من القاعدة، لا uniqid() يتغيّر مع كل قراءة —
        // فيُعيد المتصفّح رسمَ الرسائل كلّها كأنّها جديدة.
        $messages = AssistantMessage::of(auth()->id())
            ->get()
            ->map(fn (AssistantMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'at' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        return response()->json(['messages' => $messages]);
    }

    public function clear(): JsonResponse
    {
        // محادثة هذا الموظّف وحده — لا محادثة غيره
        AssistantMessage::where('user_id', auth()->id())->delete();

        // وما بقي في الجلسات القديمة من قبل الحفظ في القاعدة
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
