<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ClientPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * رسائلُ التحقّق عربيّةٌ في كلّ نموذج.
 *
 * لم يكن في lang/ar ملفُّ validation: نموذجُ الرفع بعنوانٍ فارغٍ وملفّ ‎.txt‎
 * كان يكتب «validation.required» و«validation.mimes» محلّياً، وفي الإنتاج —
 * حيث اللغةُ الاحتياطيّة en — «The title field is required.». وكذلك كلُّ
 * ‎$request->validate‎ في المكتب، ودخولُ بوابة الموكّل معها.
 */
class ArabicValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        // كالإنتاج: الاحتياطيُّ إنجليزيّ — فما لا سطرَ عربيّاً له يخرج إنجليزيّاً
        config(['app.fallback_locale' => 'en']);
    }

    public function test_the_upload_form_errors_are_arabic_with_field_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'ar']);

        $this->actingAs($admin)->from(route('documents.index'))->post(route('documents.store'), [
            'title' => '', 'access_level' => 'all',
            'file' => UploadedFile::fake()->create('notes.txt', 3, 'text/plain'),
        ])->assertRedirect(route('documents.index'));

        $errors = session('errors');
        $this->assertSame('حقل العنوان مطلوب.', $errors->first('title'));
        $this->assertStringStartsWith('يجب أن يكون نوع الملف في حقل الملف أحد:', $errors->first('file'));
    }

    /**
     * الخبرُ لا يتبع جنسَ اسم الخانة ولا العددُ معدودَه.
     *
     * كان «:attribute مطلوب.» و«:attribute المختار غير موجود.» مذكّرين أبداً
     * وأسماءُ الخانات نصفُها مؤنّث: «كلمة المرور مطلوب.»، «القضية المختار غير
     * موجود.»، «صلاحية الوصول المختار غير صالح.». والمعدودُ ثابتٌ بعد العدد:
     * «عن 40 أحرف» و«10 حرفاً».
     */
    public function test_messages_agree_with_feminine_fields_and_any_count(): void
    {
        app()->setLocale('ar');

        $errors = Validator::make(
            ['password' => '', 'case_id' => 999, 'access_level' => 'x', 'token' => 'abc', 'code' => str_repeat('1', 11)],
            ['password' => 'required', 'case_id' => 'exists:cases,id', 'access_level' => 'in:all,team,private', 'token' => 'string|min:40', 'code' => 'string|max:10'],
        )->errors();

        $this->assertSame('حقل كلمة المرور مطلوب.', $errors->first('password'));
        $this->assertSame('القيمة المختارة في حقل القضية غير موجودة.', $errors->first('case_id'));
        $this->assertSame('القيمة المختارة في حقل صلاحية الوصول غير صالحة.', $errors->first('access_level'));
        $this->assertSame('يجب ألّا يقلّ عدد أحرف حقل token عن 40.', $errors->first('token'));
        $this->assertSame('يجب ألّا يزيد عدد أحرف حقل الرمز على 10.', $errors->first('code'));
    }

    /** وفي كلّ سطرٍ لا الثلاثةِ المقتبسة وحدها: لا مسنَدَ إليه يتبع الاسم، ولا معدودَ بعد عدد. */
    public function test_no_arabic_line_lets_the_field_name_or_the_number_pick_the_grammar(): void
    {
        $ar = require lang_path('ar/validation.php');
        unset($ar['custom'], $ar['attributes']);

        foreach (\Illuminate\Support\Arr::dot($ar) as $rule => $text) {
            // ‏:attribute و:other مضافٌ إليه أبداً: «حقل …» أو مصدرٌ («قبول …»، «رفض …»، «تأكيد …»)
            preg_match_all('/(\S+)\s+:(attribute|other)\b/u', $text, $m, PREG_SET_ORDER);
            // ‏current_password وحده جملةٌ ثابتة بلا اسم خانة
            $this->assertTrue($m !== [] || $rule === 'current_password', "سطرُ «{$rule}» بلا :attribute");
            foreach ($m as [, $before]) {
                $this->assertContains($before, ['حقل', 'قبول', 'رفض', 'تأكيد'], "سطرُ «{$rule}» يجعل اسمَ الخانة مسنَداً إليه: {$text}");
            }
            $this->assertDoesNotMatchRegularExpression('/^:attribute/u', $text, "سطرُ «{$rule}» يبدأ باسم الخانة: {$text}");

            $this->assertDoesNotMatchRegularExpression(
                '/:(min|max|value|size|digits|decimal)\s+(أحرف|حرف|حرفاً|عناصر|عنصر|أرقام|رقم|منازل)/u',
                $text,
                "سطرُ «{$rule}» يضع معدوداً ثابتاً بعد العدد: {$text}"
            );
        }
    }

    /**
     * واللغةُ الإنجليزيّة لها ملفُّها كذلك: بدونه سقط فحصُ تكافؤ اللغتين
     * (TranslationKeysTest) بـ238 مفتاحاً عربيّاً بلا مقابل.
     */
    public function test_english_has_the_same_validation_keys_and_field_names(): void
    {
        $ar = \Illuminate\Support\Arr::dot(require lang_path('ar/validation.php'));
        $en = \Illuminate\Support\Arr::dot(require lang_path('en/validation.php'));
        $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($en))));
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ar))));

        app()->setLocale('en');
        $errors = Validator::make(['case_id' => ''], ['case_id' => 'required'])->errors();
        $this->assertSame('The case field is required.', $errors->first('case_id'));
    }

    public function test_every_laravel_rule_has_an_arabic_line(): void
    {
        $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $ar = require lang_path('ar/validation.php');

        $missing = array_diff(array_keys($en), array_keys($ar));
        $this->assertSame([], array_values($missing), 'قواعدُ بلا سطرٍ عربيّ تخرج إنجليزيّةً في الإنتاج');

        foreach ($ar as $rule => $line) {
            if (in_array($rule, ['custom', 'attributes'], true)) {
                continue;
            }
            foreach ((array) $line as $text) {
                $this->assertDoesNotMatchRegularExpression('/\bThe\b|\bfield\b/', $text, "سطرُ «{$rule}» ما زال إنجليزيّاً");
            }
        }
    }

    public function test_the_portal_login_error_is_arabic(): void
    {
        Setting::set(ClientPortal::KEY_ENABLED, '1', 'client_portal');
        RateLimiter::clear('client-portal:lookup:127.0.0.1');
        // الزائرُ بلا حساب: لغتُه لغةُ المكتب (config/app.php ‏'ar')
        config(['app.locale' => 'ar']);

        $this->from(route('client.access'))->post(route('client.access'), ['national_id' => ''])
            ->assertSessionHasErrors(['national_id' => 'حقل رقم الهوية مطلوب.']);
    }
}
