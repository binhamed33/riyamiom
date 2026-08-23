<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AiSettings;
use App\Support\OmaniLaw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * المساعد القانوني: عُمانيّ النطاق، ولا يخترع مادة.
 *
 * كانت التعليمة تقول «لا تختلق نصوص مواد» ولا تعطي النموذج ما يستشهد
 * به — فيبقى معتمداً على ذاكرته وحدها، وهو ما تمنعه المواصفات. الآن
 * تُحقن مراجع عُمانية موثوقة (اسم القانون ورقم مرسومه ومبدؤه) مطابِقة
 * للسؤال، وحين لا يطابق شيء يُقال له صراحةً أن يعترف بعدم امتلاك مصدر.
 */
class OmaniLegalAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function lawyer(): User
    {
        return User::factory()->create(['role' => 'lawyer', 'is_active' => true]);
    }

    /** يلتقط تعليمة النظام المرسلة فعلاً إلى المزوّد */
    private function promptFor(string $question): string
    {
        AiSettings::store('gemini', 'AIzaTestKey123', 'gemini-3.6-flash');

        $captured = '';
        Http::fake(function ($request) use (&$captured) {
            $body = json_decode($request->body(), true);
            $captured = $body['systemInstruction']['parts'][0]['text'] ?? '';

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'جواب.']]]]],
            ], 200);
        });

        $this->actingAs($this->lawyer())
            ->postJson(route('assistant.chat'), ['message' => $question]);

        return $captured;
    }

    public function test_a_labour_question_carries_the_labour_law_and_its_decree(): void
    {
        $prompt = $this->promptFor('ما حقوق العامل عند إنهاء الخدمة؟');

        $this->assertStringContainsString('قانون العمل', $prompt);
        $this->assertStringContainsString('53/2023', $prompt,
            'رقم المرسوم لم يصل النموذج — فسيستشهد من ذاكرته');
    }

    public function test_a_procedure_question_carries_the_procedure_law(): void
    {
        $prompt = $this->promptFor('ما ميعاد استئناف الحكم؟');

        $this->assertStringContainsString('قانون الإجراءات المدنية والتجارية', $prompt);
        $this->assertStringContainsString('29/2002', $prompt);
    }

    public function test_an_unmatched_question_is_told_to_admit_it_has_no_source(): void
    {
        // أخطر حالة: سؤال لا مرجع له. الصمت هنا يعني ترك النموذج
        // يخترع، فيُقال له صراحةً أن يعترف.
        $prompt = $this->promptFor('ما حكم استخدام الطائرات المسيّرة في المسح العقاري؟');

        $this->assertStringContainsString('لا توجد في قاعدة المعرفة مراجع مطابقة', $prompt,
            'السؤال بلا مرجع لم يُقَل للنموذج ذلك — فسيملأ الفراغ باختراع');
        $this->assertStringContainsString('لا تملك مصدراً موثوقاً', $prompt);

        // ولا يُحقَن مرجعٌ مقحم لمجرّد ملء الفراغ
        $this->assertStringNotContainsString('مراجع قانونية عُمانية موثوقة ذات صلة', $prompt);
    }

    public function test_the_prompt_refuses_other_jurisdictions_explicitly(): void
    {
        $prompt = $this->promptFor('ما هو قانون العمل؟');

        $this->assertStringContainsString('سلطنة عُمان', $prompt);
        $this->assertStringContainsString('الإمارات', $prompt,
            'لا توجد قاعدة صريحة ترفض قوانين الدول الأخرى');
    }

    public function test_the_prompt_forbids_guaranteeing_an_outcome(): void
    {
        $prompt = $this->promptFor('هل أكسب قضيتي؟');

        $this->assertStringContainsString('ضماناً بنتيجة دعوى', $prompt);
    }

    public function test_only_relevant_references_are_injected_not_the_whole_book(): void
    {
        // حشو كل القوانين في كل سؤال يُشتّت الإجابة ويستهلك السياق
        $prompt = $this->promptFor('ما حقوق العامل عند إنهاء الخدمة؟');

        $this->assertStringContainsString('قانون العمل', $prompt);
        $this->assertStringNotContainsString('قانون الأحوال الشخصية', $prompt,
            'حُقنت مراجع لا صلة لها بالسؤال');
    }

    public function test_every_reference_carries_a_law_name_and_a_decree_number(): void
    {
        foreach (OmaniLaw::references() as $ref) {
            $this->assertNotEmpty($ref['law']);
            $this->assertMatchesRegularExpression('#\d+/\d+#', $ref['decree'],
                "المرجع «{$ref['law']}» بلا رقم مرسوم — لا يصلح للاستشهاد");
            $this->assertNotEmpty($ref['keywords'], "المرجع «{$ref['law']}» لا يُطابقه سؤال أبداً");
        }
    }

    public function test_arabic_spelling_variants_still_match(): void
    {
        // «دعوى» و«دعوي»، و«الاجراءات» بلا همزة — الموظّف يكتب بسرعة
        $this->assertNotEmpty(OmaniLaw::matching('كيف ارفع دعوي؟'));
        $this->assertNotEmpty(OmaniLaw::matching('اجراءات التنفيذ'));
    }
}
