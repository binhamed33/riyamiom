<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Ai\GeminiProvider;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * تقاعُد نموذج عند المزوّد لا يُعطّل المكتب.
 *
 * ما جرى فعلاً في الإنتاج، وردُّ Google حرفياً:
 *
 *   "This model models/gemini-2.5-flash is no longer available to new
 *    users. Please update your code to use models/gemini-3.6-flash"
 *
 * والنموذج كان يظهر في قائمة النماذج — أي أن القائمة لا تكفي: تسرد
 * ما هو موجود لا ما هو متاحٌ لهذا المفتاح. وقد أوهمتني القائمة أن
 * العطل ليس في النموذج.
 *
 * والمزوّد يسمّي بديله في نصّ ردّه، وهو أدقّ من أي قائمة نكتبها نحن:
 * قائمتنا تتقادم وهو يعرف بديله.
 */
class AiModelRetirementTest extends TestCase
{
    use RefreshDatabase;

    private const RETIRED = 'gemini-2.5-flash';
    private const REPLACEMENT = 'gemini-3.6-flash';

    protected function setUp(): void
    {
        parent::setUp();

        AiSettings::store('gemini', 'AIzaTestKey123', self::RETIRED);
    }

    private function retirementBody(): array
    {
        return ['error' => [
            'code' => 404,
            'message' => 'This model models/' . self::RETIRED
                . ' is no longer available to new users. Please update your code to use models/'
                . self::REPLACEMENT . ' for the latest features and improvements.',
            'status' => 'NOT_FOUND',
        ]];
    }

    public function test_a_retired_model_falls_through_to_the_one_the_provider_names(): void
    {
        Http::fake([
            '*/models/' . self::RETIRED . ':generateContent*' => Http::response($this->retirementBody(), 404),
            '*/models/' . self::REPLACEMENT . ':generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'مرحباً بك في مُداوَلة.']]]]],
            ], 200),
            '*' => Http::response(['error' => ['code' => 404]], 404),
        ]);

        $answer = (new GeminiProvider())->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        $this->assertSame('مرحباً بك في مُداوَلة.', $answer,
            'تقاعُد النموذج عطّل المساعد بدل أن يُتبع البديل');
    }

    public function test_the_office_remembers_the_working_model_instead_of_failing_daily(): void
    {
        Http::fake([
            '*/models/' . self::RETIRED . ':generateContent*' => Http::response($this->retirementBody(), 404),
            '*/models/' . self::REPLACEMENT . ':generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'أهلاً.']]]]],
            ], 200),
            '*' => Http::response(['error' => ['code' => 404]], 404),
        ]);

        $this->assertSame(self::RETIRED, AiSettings::model());

        (new GeminiProvider())->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        $this->assertSame(self::REPLACEMENT, AiSettings::model(),
            'المكتب سيبدأ من النموذج الميّت في كل طلب إلى الأبد');
    }

    public function test_a_bad_key_is_not_mistaken_for_a_retired_model(): void
    {
        // 403 ليس 404: المفتاح مرفوض، وتجريب نماذج أخرى عبثٌ يُبطئ الردّ
        Http::fake(['*' => Http::response(['error' => ['code' => 403, 'message' => 'API key not valid']], 403)]);

        $provider = new GeminiProvider();
        $answer = $provider->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        $this->assertNull($answer);
        $this->assertSame(self::RETIRED, AiSettings::model(), 'مفتاحٌ خاطئ غيّر النموذج المضبوط');
    }

    public function test_the_stored_model_is_untouched_when_it_still_works(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'أهلاً.']]]]],
        ], 200)]);

        (new GeminiProvider())->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        $this->assertSame(self::RETIRED, AiSettings::model(),
            'النموذج العامل بُدِّل بلا سبب');
    }

    public function test_the_key_itself_is_never_written_to_the_settings_by_the_heal(): void
    {
        $before = Setting::get(AiSettings::KEY_API_KEY);

        Http::fake([
            '*/models/' . self::RETIRED . ':generateContent*' => Http::response($this->retirementBody(), 404),
            '*/models/' . self::REPLACEMENT . ':generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'أهلاً.']]]]],
            ], 200),
            '*' => Http::response([], 404),
        ]);

        (new GeminiProvider())->chat([['role' => 'user', 'content' => 'اهلا']], 'نظام');

        $this->assertSame($before, Setting::get(AiSettings::KEY_API_KEY),
            'الشفاء الذاتي مسّ المفتاح — وهو لا يخصّه');
    }
}
