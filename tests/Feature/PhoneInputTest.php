<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * حقلُ الهاتف: دولةٌ تُختار، وطولٌ يتبعها، وصيغةٌ واحدةٌ تُحفظ.
 *
 * ═══ ما وقع على شاشة المالك ═══
 *
 * موكّلٌ سعوديٌّ حقيقيّ كُتب رقمه فرُدّ بـ«رقم هاتف غير صحيح». والقاعدةُ
 * القديمة تعرف ستَّ دولٍ خليجيّةٍ وأطوالَها، فكانت تردّ:
 *
 *   • الرقمَ السعوديَّ الصحيح إن كُتب بصفره المحلّيّ،
 *   • ورقمَ الهند وبريطانيا وأمريكا ومصر كلَّها — وليست في الجدول،
 *   • ولا تقول في أيٍّ من ذلك ما العيبُ ولا كم المطلوب.
 *
 * ومكتبُ المحاماة يخاصم شركاتٍ ويمثّل مقيمين: خصمٌ في الهند، وشركةٌ في
 * لندن، وموكّلٌ مصريّ. فالجدولُ الخليجيُّ يردّ عملَ المكتب نفسَه.
 *
 * ═══ ما يحرسه هذا ═══
 *
 * ١) أنّ الدولةَ تُختار من مئتين وخمسٍ وأربعين لا من ستّ.
 * ٢) وأنّ الطولَ المقبول يتبع الدولةَ المختارة — لا طولاً واحداً للدنيا.
 * ٣) وأنّ ما يُحفظ صيغةٌ واحدةٌ لا تلتبس: ‎+966552000531‎.
 * ٤) وأنّ الرسالةَ تقول أيَّ دولةٍ قيست وكم تريد وكم كُتب.
 * ٥) وأنّ الحقلَ يعمل بلا جافاسكربت — قائمةٌ أصليّةٌ لا زرٌّ معطَّل.
 */
class PhoneInputTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function client(array $attrs = []): Client
    {
        return Client::create(array_merge([
            'name' => 'موكّل',
            'type' => 'individual',
            'national_id' => '1234567',
            'phone' => '96891234567',
        ], $attrs));
    }

    // ── ١) الحقل كما يُرسم ────────────────────────────────────────

    /** @return array<string, array{0: string}> */
    public static function forms(): array
    {
        return [
            'موكّل جديد' => ['/clients/create'],
            'مستخدم جديد' => ['/users/create'],
            'ملفّي' => ['/profile'],
            'قضيّة جديدة' => ['/cases/create'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forms')]
    public function test_every_phone_form_carries_a_real_country_picker(string $path): void
    {
        $html = $this->actingAs($this->admin())->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('data-phone-field', $html, $path . ' — لا حقلَ هاتفٍ مركَّب');
        $this->assertStringContainsString('data-phone-country', $html, $path . ' — لا منتقيَ دولة');
        $this->assertStringContainsString('data-phone-national', $html, $path . ' — لا حقلَ رقم');

        // ‏٢٤٥ دولةً لا ستّ: يُعَدّ ما رُسم فعلاً لا ما في الجدول
        $options = substr_count($html, 'data-dial="');
        $this->assertGreaterThan(200, $options,
            $path . " — المنتقي فيه {$options} دولةً فقط");

        // ولكلّ دولةٍ أطوالُها ومثالُها معها، وإلّا فالحقلُ لا يعرف «كم رقماً»
        $this->assertStringContainsString('data-len="8"', $html, $path . ' — أطوالُ عُمان غائبة');
        $this->assertStringContainsString('data-ex="', $html, $path . ' — لا مثالَ لأيّ دولة');
    }

    /**
     * ويعمل بلا سكربت: ما يُرسَل قائمةٌ أصليّةٌ وحقلُ رقم.
     *
     * زرُّ المنتقي مخفيٌّ حتى يعمل السكربتُ فيُظهره. فلو سقط السكربت —
     * حاجبُ إعلانات، شبكةٌ بطيئة، سياسةُ أمنٍ ترفض — بقي الحقلُ عاملاً
     * لا زرّاً لا يفتح.
     */
    public function test_the_field_still_works_without_javascript(): void
    {
        $html = $this->actingAs($this->admin())->get('/clients/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<select[^>]+name="phone_country"/', $html,
            'الدولةُ لا تُرسَل من قائمةٍ أصليّة — فبلا سكربتٍ لا دولةَ أصلاً',
        );

        $this->assertMatchesRegularExpression(
            '/data-phone-trigger[^>]*style="display:none"/', $html,
            'زرُّ المنتقي ظاهرٌ قبل أن يعمل السكربت — فيُضغط ولا يفتح',
        );
    }

    /** والسكربتُ مرّةً واحدةً مهما تعدّدت الحقول في الصفحة. */
    public function test_the_picker_script_is_emitted_once_per_page(): void
    {
        // ‏@error يقرأ حقيبةَ الأخطاء التي يشاركها وسيطُ الجلسة، ولا
        // وسيطَ في تصييرٍ مباشر
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = Blade::render(
            '<x-phone-input name="phone" /><x-phone-input name="opponent_phone" />'
        );

        $this->assertSame(2, substr_count($html, '<div data-phone-field'), 'حقلان لم يُرسما');
        $this->assertSame(1, substr_count($html, 'function lengthWord'),
            'السكربتُ مكرَّرٌ بعدد الحقول في الصفحة');
    }

    /** والقيمةُ المحفوظةُ تعود إلى مكانها: الدولةُ في المنتقي والباقي في الحقل. */
    public function test_a_saved_number_splits_back_into_its_country_and_its_digits(): void
    {
        $client = $this->client(['phone' => '+966552000531']);

        $html = $this->actingAs($this->admin())->get('/clients/' . $client->id . '/edit')
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="SA"[^>]*selected/', $html,
            'الدولةُ المحفوظةُ ليست مختارةً في المنتقي');
        $this->assertMatchesRegularExpression('/data-phone-national[^>]*/', $html);
        $this->assertStringContainsString('value="552000531"', $html,
            'الحقلُ يعرض المفتاحَ مع الرقم — فيُكتب المفتاحُ مرّتين عند الحفظ');
    }

    /** ورقمٌ قديمٌ محفوظٌ بمفتاحه بلا «+» يُفهم كذلك. */
    public function test_a_legacy_number_saved_without_a_plus_is_still_read_correctly(): void
    {
        $client = $this->client(['phone' => '971501234567']);

        $html = $this->actingAs($this->admin())->get('/clients/' . $client->id . '/edit')
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="AE"[^>]*selected/', $html);
        $this->assertStringContainsString('value="501234567"', $html);
    }

    /**
     * ═══ الأعلامُ لا تُرسم على ويندوز ═══
     *
     * العلمُ في يونيكود حرفا «مؤشّرٍ إقليميّ» يرسمهما الخطُّ علماً.
     * وخطُّ ويندوز — Segoe UI Emoji — يحذف الأعلام **عمداً**، فيسقط
     * المتصفّح إلى رسم الحرفين كما هما: «OM» و«AE» و«SA» صفّاً في وجه
     * الموظّف بدل الأعلام.
     *
     * ولا حيلةَ في الكود: الجهازُ لا يملك الرسم. فيُحمَل الخطُّ معنا،
     * مقصوصاً على الأعلام وحدها، من نطاقنا نفسِه — لا شبكةَ خارجيّةً
     * تُفتح ولا سياسةَ أمنٍ تُوسَّع.
     *
     * وهذا يحرس الملفَّ من أن يُحذف بصمت: من نقله أو أعاد تسميته يسقط
     * هنا، لا في شاشة موظّفٍ بعد شهر.
     */
    public function test_the_flag_font_ships_with_the_app(): void
    {
        $font = public_path('fonts/TwemojiCountryFlags.woff2');

        $this->assertFileExists($font, 'خطُّ الأعلام غيرُ موجود — ويندوز سيعرض «OM» و«AE» بدلها');
        $this->assertGreaterThan(50_000, filesize($font), 'ملفُّ الخطّ أصغرُ من أن يكون كاملاً');

        $html = $this->actingAs($this->admin())->get('/clients/create')->assertOk()->getContent();

        $this->assertStringContainsString('fonts/TwemojiCountryFlags.woff2', $html,
            'الصفحةُ لا تشير إلى الخطّ');
        $this->assertStringContainsString("font-family: 'Twemoji Country Flags'", $html);
    }

    /**
     * والخطُّ مقصورٌ على خانة العلم — لا على النصّ العربيّ.
     *
     * قاعدةٌ على الحقل كلِّه تضع خطَّ الأعلام في مقدّمة قائمة خطوطه،
     * وهو لا يحمل حرفاً عربياً ولا لاتينياً. و‎unicode-range يحرس ذلك
     * في المتصفّح، لكنّ الحراسةَ حارسان أولى: القاعدةُ على العلم وحدَه،
     * والمدى فوقها.
     */
    public function test_the_flag_font_never_touches_the_arabic_text(): void
    {
        $css = file_get_contents(resource_path('views/partials/phone-picker.blade.php'));

        $this->assertStringContainsString('.phone-flag {', $css,
            'قاعدةُ الخطّ ليست مقصورةً على خانة العلم');
        $this->assertStringNotContainsString("[data-phone-field] {
    font-family: 'Twemoji", $css);

        // والمدى يقصر التنزيلَ والاستعمالَ على المؤشّرات الإقليميّة
        $this->assertStringContainsString('unicode-range: U+1F1E6-1F1FF', $css,
            'بلا مدىً يُنزَّل الخطُّ لكلّ صفحةٍ ويُقحَم في قياس كلّ حرف');

        // وكلُّ علمٍ يُعرض يحمل الصنف: الزرُّ من القالب، والصفوفُ من السكربت
        $html = $this->actingAs($this->admin())->get('/clients/create')->assertOk()->getContent();

        $this->assertStringContainsString('data-phone-flag class="phone-flag', $html,
            'علمُ الزرّ بلا صنف — يبقى حرفين على ويندوز');
        // موضعان: علمُ الزرّ في القالب، وقالبُ الصفّ في السكربت
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'phone-flag text-base'),
            'أعلامُ صفوف اللوحة بلا صنف — تبقى حرفين على ويندوز');
    }

    // ── ٢) ما يُحفظ ───────────────────────────────────────────────

    /**
     * الرقمُ المحلّيُّ مع دولته يُحفظ بصيغةٍ دوليّةٍ واحدة.
     *
     * ثلاثُ صيغٍ للرقم الواحد في القاعدة تعني: بحثٌ لا يجد، وواتساب لا
     * يصل. فالصيغةُ واحدةٌ عند الحفظ لا عند القراءة.
     */
    public function test_the_chosen_country_and_the_local_digits_are_stored_as_one_form(): void
    {
        $this->actingAs($this->admin())->post('/clients', [
            'name' => 'موكّل سعوديّ',
            'type' => 'individual',
            'phone' => '0552000531',
            'phone_country' => 'SA',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+966552000531', Client::where('name', 'موكّل سعوديّ')->first()->phone);
    }

    /** والصفرُ المحلّيُّ يُقشَّر بقاعدة تلك الدولة لا بتخمين. */
    public function test_the_local_trunk_zero_is_stripped_by_the_countrys_own_rule(): void
    {
        $this->actingAs($this->admin())->post('/clients', [
            'name' => 'موكّل عُمانيّ',
            'type' => 'individual',
            'phone' => '92123456',
            'phone_country' => 'OM',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+96892123456', Client::where('name', 'موكّل عُمانيّ')->first()->phone);
    }

    /**
     * ═══ ما كان يُردّ وهو صحيح ═══
     *
     * كلُّ هذه أرقامٌ صحيحةٌ في دولتها، وكلُّها كانت تُردّ لأنّ الجدولَ
     * خليجيّ. والمكتبُ يخاصم شركةً في لندن ويمثّل مقيماً هندياً.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function worldNumbers(): array
    {
        return [
            'الهند' => ['IN', '8123456789', '+918123456789'],
            'بريطانيا' => ['GB', '7400123456', '+447400123456'],
            'أمريكا' => ['US', '2015550123', '+12015550123'],
            'مصر' => ['EG', '1001234567', '+201001234567'],
            'باكستان' => ['PK', '3012345678', '+923012345678'],
            'الفلبّين' => ['PH', '9051234567', '+639051234567'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('worldNumbers')]
    public function test_the_world_outside_the_gulf_is_no_longer_refused(
        string $iso, string $national, string $expected,
    ): void {
        $this->actingAs($this->admin())->post('/clients', [
            'name' => 'موكّل ' . $iso,
            'type' => 'individual',
            'phone' => $national,
            'phone_country' => $iso,
        ])->assertSessionHasNoErrors();

        $this->assertSame($expected, Client::where('name', 'موكّل ' . $iso)->first()->phone);
    }

    // ── ٣) ما يُردّ، وكيف يُقال ───────────────────────────────────

    /**
     * الرقمُ الذي رُدَّ على المالك: خانةٌ زائدةٌ فوق تسع السعوديّة.
     *
     * ويُردّ اليوم أيضاً — لكن برسالةٍ تقول ما العيب.
     */
    public function test_the_refusal_names_the_country_and_counts_the_digits(): void
    {
        $response = $this->actingAs($this->admin())->post('/clients', [
            'name' => 'موكّل',
            'type' => 'individual',
            'phone' => '5552000531',
            'phone_country' => 'SA',
        ]);

        $message = (string) session('errors')->first('phone');

        $this->assertStringContainsString('السعودية', $message, 'الرسالةُ لا تقول أيَّ دولةٍ قيست');
        $this->assertStringContainsString('٩', $message, 'الرسالةُ لا تقول كم رقماً تريد');
        $this->assertStringContainsString('١٠', $message, 'الرسالةُ لا تقول كم كُتب');

        $this->assertDatabaseCount('clients', 0);
    }

    /** والدولةُ المُسمّاةُ تُصدَّق: من قال «عُمان» لا يُحفظ رقمُه هندياً. */
    public function test_a_named_country_is_believed_over_a_guess(): void
    {
        $this->actingAs($this->admin())->post('/clients', [
            'name' => 'موكّل',
            'type' => 'individual',
            'phone' => '912345678901',
            'phone_country' => 'OM',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('clients', 0);
    }

    /** والخطأُ يعود كما كُتب: لا مقصوصاً ولا مبدَّلاً، ليراه صاحبُه. */
    public function test_a_refused_number_comes_back_exactly_as_it_was_typed(): void
    {
        $this->actingAs($this->admin())->post('/clients', [
            'name' => 'موكّل',
            'type' => 'individual',
            'phone' => '5552000531',
            'phone_country' => 'SA',
        ]);

        $this->assertSame('5552000531', session('_old_input')['phone'] ?? null,
            'الرقمُ المردودُ عاد مغيَّراً — فيصحّح الموظّفُ ما لم يكتبه');
    }

    // ── ٤) الوسيطُ لا يمسّ ما ليس له ──────────────────────────────

    /**
     * حقلٌ مرافقٌ لا يخصّ الهاتف لا يُبدَّل.
     *
     * الوسيطُ يعمل على كلّ حقلٍ اسمُه ‎{س}_country‎ — وهو نمطٌ عامٌّ عن
     * قصد كي لا يُنسى نموذجٌ جديد. وثمنُه أن يُحرَس: لا يُكتب شيءٌ إلا
     * إذا خرج رقمٌ صحيح.
     */
    public function test_the_middleware_leaves_a_field_that_is_not_a_phone_alone(): void
    {
        $middleware = new \App\Http\Middleware\NormalizePhoneNumbers();

        $request = \Illuminate\Http\Request::create('/x', 'POST', [
            'birth_country' => 'OM',
            'birth' => 'مسقط',
            'phone_country' => 'ZZ',
            'phone' => '92123456',
        ]);

        $middleware->handle($request, fn ($r) => new \Illuminate\Http\Response());

        $this->assertSame('مسقط', $request->input('birth'), 'حقلٌ ليس هاتفاً بُدِّل');
        $this->assertSame('92123456', $request->input('phone'),
            'رمزُ دولةٍ لا وجودَ له قُبل، فبُدّل الرقمُ على أساسه');
    }

    /** والطلبُ الذي لا يحمل دولةً يمرّ كما كان — نموذجٌ قديمٌ أو استيراد. */
    public function test_a_request_without_a_country_passes_through_untouched(): void
    {
        $middleware = new \App\Http\Middleware\NormalizePhoneNumbers();

        $request = \Illuminate\Http\Request::create('/x', 'POST', ['phone' => '92123456']);
        $middleware->handle($request, fn ($r) => new \Illuminate\Http\Response());

        $this->assertSame('92123456', $request->input('phone'));

        // ويبقى مقبولاً في التحقّق: المكتبةُ تقرأ مفتاحَه من نفسِه
        $this->actingAs($this->admin())->post('/clients', [
            'name' => 'بلا منتقٍ',
            'type' => 'individual',
            'phone' => '96891234567',
        ])->assertSessionHasNoErrors();
    }

    // ── ٥) بقيّةُ النماذج ─────────────────────────────────────────

    /** وخصمُ القضيّة كذلك: شركةٌ في لندن خصمٌ كما الجارُ في مسقط. */
    public function test_the_opponent_phone_takes_the_world_too(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)->post('/cases', [
            'case_number' => 'ق/1',
            'title' => 'قضيّة',
            'description' => 'و',
            'type' => 'مدني',
            'court' => 'المحكمة العليا',
            'opponent' => 'شركةٌ في لندن',
            'opponent_phone' => '7400123456',
            'opponent_phone_country' => 'GB',
            'status' => 'active',
            'priority' => 'medium',
            'client_id' => $client->id,
            'lawyer_id' => $admin->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('+447400123456', LegalCase::first()->opponent_phone);
    }

    /** ومنتقي الدولة في نموذج القضيّة يحمل حقلَه هو لا حقلَ الموكّل. */
    public function test_the_opponent_picker_carries_its_own_companion_field(): void
    {
        $html = $this->actingAs($this->admin())->get('/cases/create')->assertOk()->getContent();

        $this->assertStringContainsString('name="opponent_phone_country"', $html);
    }

    // ── ٦) الأطوالُ نفسُها ────────────────────────────────────────

    /**
     * كلُّ دولةٍ في المنتقي تعرف أطوالَها ومثالَها.
     *
     * ومثالُها يجب أن يكون رقماً صحيحاً فيها — وإلّا فالحقلُ يعرض في
     * تلميحه رقماً لو كُتب لرُدّ.
     */
    public function test_every_country_in_the_picker_knows_its_own_lengths(): void
    {
        $countries = Phone::countries('ar');

        $this->assertGreaterThan(200, count($countries));

        $noLengths = [];
        $badExample = [];

        foreach ($countries as $c) {
            if ($c['lengths'] === []) {
                $noLengths[] = $c['iso'];
            }

            if ($c['example'] !== '' && !Phone::isValid($c['example'], $c['iso'])) {
                $badExample[] = $c['iso'];
            }
        }

        $this->assertSame([], $noLengths, 'دولٌ بلا أطوالٍ معروفة: ' . implode(', ', $noLengths));
        $this->assertSame([], $badExample, 'مثالٌ لو كُتب لرُدّ: ' . implode(', ', $badExample));
    }

    /**
     * ═══ قائمةٌ محفوظةٌ بشكلٍ قديم لا تُقرأ ═══
     *
     * القائمةُ تُحفظ في الذاكرة يوماً كاملاً. ويوم أُضيفت الأطوالُ إلى
     * صفوفها بقي المحفوظُ من قبلُ على شكله الأوّل، فقرأه القالبُ الجديد
     * وسقط بـ«Undefined array key: lengths» — في **كلّ نموذجٍ فيه هاتف**
     * حتى تنتهي مدّةُ الحفظ. ولم يمسكه اختبار: الذاكرةُ في الاختبارات
     * تبدأ فارغةً في كلّ مرّة، فلا شكلَ قديمَ فيها أصلاً.
     *
     * ومسحُ الذاكرة عند النشر علاجٌ يُنسى مرّةً فيقع العطبُ في مكتبٍ
     * لا نراه. فالمفتاحُ صار يحمل بصمةَ أسماء الحقول: من غيّرها غيّر
     * المفتاح ولو لم ينتبه.
     */
    public function test_a_list_cached_in_an_older_shape_is_never_read(): void
    {
        $stale = [['iso' => 'OM', 'name' => 'عُمان', 'dial' => 968, 'flag' => '🇴🇲', 'example' => '92123456']];

        // كلُّ مفتاحٍ محتملٍ من الشكل القديم — بلا بصمةٍ وببصمةٍ أخرى
        cache()->put('phone.countries.ar', $stale, 3600);
        cache()->put('phone.countries.' . substr(md5('iso,name,dial,flag,example'), 0, 8) . '.ar', $stale, 3600);

        foreach (Phone::countries('ar') as $row) {
            $this->assertArrayHasKey('lengths', $row, 'قُرئ صفٌّ بشكلٍ قديم — كلُّ نموذجٍ فيه هاتفٌ يسقط');
            $this->assertArrayHasKey('max', $row);
            $this->assertArrayHasKey('main', $row);
            $this->assertArrayHasKey('q', $row);
        }

        $this->assertGreaterThan(200, count(Phone::countries('ar')), 'قُرئت القائمةُ القديمة بصفٍّ واحد');
    }

    /** والمقدَّماتُ أوّلاً: عُمانُ في رأس القائمة لا في وسط المئتين. */
    public function test_the_countries_we_use_most_come_first(): void
    {
        $countries = Phone::countries('ar');

        $this->assertSame('OM', $countries[0]['iso'], 'عُمان ليست أوّلَ القائمة');
        $this->assertSame(968, $countries[0]['dial']);
        $this->assertSame('🇴🇲', $countries[0]['flag']);

        $head = array_column(array_slice($countries, 0, count(Phone::PINNED)), 'iso');
        $this->assertSame(Phone::PINNED, $head, 'المقدَّماتُ ليست في مقدّمة القائمة');
    }

    /** ولكلٍّ اسمٌ بلغة الواجهة — لا رمزان بحرفين. */
    public function test_the_countries_are_named_in_the_interface_language(): void
    {
        $ar = collect(Phone::countries('ar'))->keyBy('iso');
        $en = collect(Phone::countries('en'))->keyBy('iso');

        $this->assertSame('عُمان', $ar['OM']['name']);
        $this->assertSame('Oman', $en['OM']['name']);

        // ونصُّ البحث يجمع اللغتين: من كتب «saudi» بالإنجليزية في واجهةٍ
        // عربيّةٍ يجدها
        $this->assertStringContainsString('saudi', $ar['SA']['q']);
        $this->assertStringContainsString('966', $ar['SA']['q']);
    }
}
