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
            'retry_of' => 'nullable|integer',
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
        //
        // وإن كانت هذه إعادةَ محاولةٍ لسؤالٍ محفوظ، أُعيد استعماله ولم
        // يُكتب ثانية: المتصفّح يُعيد الإرسال تلقائياً بعد تعثّرٍ عابر،
        // فكان السؤال يتضاعف في المحادثة ويُرسَل مكرّراً في السياق.
        // ولا يُقبل المعرّف إلا لصاحبه ولنصّ سؤاله نفسه.
        $asked = $this->reusableQuestion($request->integer('retry_of'), $userId, $userMessage)
            ?? AssistantMessage::write($userId, 'user', $userMessage);

        $history = AssistantMessage::contextFor($userId);
        $systemPrompt = $this->buildSystemPrompt($userMessage);

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

    /**
     * السؤال المحفوظ الذي تُعاد المحاولة عليه — إن كان لصاحبه ونصّه.
     *
     * الشرطان معاً: المعرّف وحده يسمح لمستخدمٍ بأن يشير إلى رسالة
     * غيره، والنصّ وحده لا يميّز سؤالين متطابقين لمستخدمين.
     */
    private function reusableQuestion(?int $id, int $userId, string $text): ?AssistantMessage
    {
        if (!$id) {
            return null;
        }

        return AssistantMessage::where('id', $id)
            ->where('user_id', $userId)
            ->where('role', 'user')
            ->where('content', $text)
            ->first();
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

    protected function buildSystemPrompt(string $question = ''): string
    {
        $cases = LegalCase::with('client')
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get();

        $casesText = $cases->map(function ($c) {
            return "- قضية رقم {$c->office_case_number} ({$c->case_type}): {$c->title} — المحكمة: {$c->court} — الحالة: {$c->status} — الموكل: {$c->client?->name}";
        })->join("\n") ?: '- لا توجد قضايا مسجلة حالياً';

        // مراجع القانون العُماني التي يمسّها هذا السؤال تحديداً.
        //
        // كانت التعليمة تقول «لا تختلق مواد» ولا تعطي النموذج ما
        // يستشهد به — فيبقى معتمداً على ذاكرته وحدها، وهو ما تمنعه
        // المواصفات صراحةً. تُحقن المداخل المطابقة فقط، لا الكتلة
        // كلها في كل طلب: حشوٌ لا صلة له بالسؤال يُشتّت الإجابة.
        $supportPhone = (string) config('mudawala.support_phone');

        $lawContext = \App\Support\OmaniLaw::contextFor($question);

        if ($lawContext === '') {
            $lawContext = 'لا توجد في قاعدة المعرفة مراجع مطابقة لهذا السؤال — التزم بشرح المبدأ العام، وقل صراحةً إنك لا تملك مصدراً موثوقاً كافياً لذكر رقم مادة بعينها.';
        }

        return <<<SYSTEM
أنت مساعد قانوني ذكي مدمج في نظام إدارة مكتب محاماة عماني، متاح لجميع موظفي المكتب. أنت محامٍ خبير في القوانين السارية في سلطنة عمان (قانون المعاملات المدنية، قانون الإجراءات المدنية والتجارية، قانون الإثبات، قانون العمل، قانون الشركات التجارية، قانون التجارة، قانون الجزاء، قانون الإجراءات الجزائية، قانون المرافعات الشرعية، قوانين التنفيذ، نظام المحاماة، وأحكام المحكمة العليا العمانية).

القضايا الحالية في المكتب (يمكنك الاستفادة منها عند الإجابة):
{$casesText}

{$lawContext}

قواعد صارمة يجب الالتزام بها:
- أجب باللغة العربية الفصحى دائماً.
- لا تجيب إطلاقاً عن أي سؤال خارج القانون أو خارج نطاق العمل القانوني (لا سياسة، لا رياضة، لا أخبار عامة، لا برمجة، لا نصائح طبية أو مالية شخصية، لا شؤون عامة غير قانونية). إذا كان السؤال خارج نطاق القانون العماني أو العمل القانوني، اعتذر بلطف واذكر أنك مخصص فقط للأسئلة القانونية والمتعلقة بقضايا المكتب، ثم وجّهه هكذا: «لأي طلب آخر يخص النظام تواصل مع مطوّر مُداوَلة مباشرة على {$supportPhone}».
- الأسئلة القانونية العامة (مدني، عمالي، تجاري، جزائي، أحوال شخصية، إجراءات، إثبات، تنفيذ) مرحب بها دائماً.
- استند في إجاباتك إلى القانون العماني فقط، واذكر القانون أو المبدأ ذي الصلة.
- إذا سُئلت عن قانون دولة أخرى (السعودية، الإمارات، قطر، الكويت، البحرين، مصر، أو غيرها)، قل صراحةً إن المساعد مخصّص للقوانين والأنظمة في سلطنة عُمان، ولا تُفتِ في قانون تلك الدولة. ويجوز أن تشير إلى وجود فرق دون تفصيله إن كان السؤال مقارنةً.
- لا تُعطِ ضماناً بنتيجة دعوى ولا تَعِد بكسبها مهما أُلحّ عليك؛ النتيجة تعود للمحكمة.
- استعمل المراجع المذكورة أعلاه إن وُجدت، ولا تذكر رقم مادة لم يرد فيها. أرقام القوانين ومراسيمها المذكورة أعلاه موثوقة ويمكن الاستشهاد بها.
- لا تختلق نصوص مواد قانونية؛ إذا لم تكن متأكداً من رقم المادة، اشرح المبدأ القانوني والمرجع العام ونبّه للتحقق من النص الرسمي.
- يمكنك الإشارة إلى قضايا المكتب المذكورة أعلاه عند السؤال عنها (مثل: حالة القضية، نوعها، المحكمة)، لكن لا تقدم معلومات تفصيلية غير موجودة في هذه القائمة، ولا تكشف معلومات سرية خارج المطلوب.
- أجب بإجابات عملية ومركزة ومختصرة قدر الإمكان.
- استخدم عناوين أو نقاط عند الحاجة لتسهيل القراءة.
SYSTEM;
    }
}
