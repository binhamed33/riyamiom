<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * كل خانة تقبل نوعها وحده: الهاتف أرقاماً، والبريد بريداً، والاسم نصاً.
 *
 * كان الهاتف «string|max:255» فيُحفظ في سجلّ الموكّل «اتصل بأخيه» ولا
 * يُكتشف إلا يوم يُحتاج الاتصال. هذه الاختبارات تُثبّت القاعدة الجديدة.
 */
class FieldValidationTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        $user = User::factory()->create(['role' => 'developer']);
        $user->is_active = true;
        $user->save();

        return $user;
    }

    // ── الصنف نفسه ───────────────────────────────────────────────

    /**
     * الرقمُ الذي رُدَّ على المالك: ‎009665552000531‎.
     *
     * خانةٌ زائدةٌ فوق التسع التي تقبلها السعوديّة. والجدولُ القديم كان
     * يردّه أيضاً — لكن لأنّه لا يعرف طولاً سعودياً بعد المفتاح، فيردّ
     * الصحيحَ والخاطئَ سواءً.
     */
    public function test_the_number_that_was_refused_is_measured_against_its_own_country()
    {
        $this->assertFalse(Phone::isValid('009665552000531'), 'خانةٌ زائدةٌ مرّت');
        $this->assertTrue(Phone::isValid('00966552000531'), 'رقمٌ سعوديٌّ صحيحٌ رُدّ');
        $this->assertSame('+966552000531', Phone::e164('00966552000531'));
        $this->assertSame('SA', Phone::region('00966552000531'));
    }

    public function test_local_number_without_country_code_is_omani()
    {
        $this->assertTrue(Phone::isValid('91234567'));
        $this->assertSame('+96891234567', Phone::e164('91234567'));
        $this->assertSame('OM', Phone::region('91234567'));
    }

    /** والصفرُ المحلّيُّ يُقشَّر بقاعدة الدولة لا بالتخمين. */
    public function test_the_local_trunk_zero_is_stripped_by_the_country_rule()
    {
        $this->assertTrue(Phone::isValid('0552000531', 'SA'));
        $this->assertSame('+966552000531', Phone::e164('0552000531', 'SA'));

        $this->assertTrue(Phone::isValid('05012345678', 'TR'));
        $this->assertSame('+905012345678', Phone::e164('05012345678', 'TR'));
    }

    /**
     * ═══ ما كان يُردّ وهو صحيح ═══
     *
     * مكتبُ المحاماة يخاصم شركاتٍ ويمثّل مقيمين: خصمٌ في الهند، وشركةٌ
     * في لندن، وموكّلٌ مصريّ. وكلُّ أرقامهم كانت تُرَدّ لأنّ الجدولَ
     * خليجيّ.
     */
    public function test_the_world_outside_the_gulf_is_no_longer_refused()
    {
        foreach ([
            '+911234567890' => 'IN',
            '+447400123456' => 'GB',
            '+12015550123' => 'US',
            '+201001234567' => 'EG',
            '+923001234567' => 'PK',
            '+639171234567' => 'PH',
        ] as $number => $iso) {
            $this->assertTrue(Phone::isValid($number), $number . ' رُدّ وهو صحيح');
            $this->assertSame($iso, Phone::region($number), $number);
        }
    }

    /** والخليجُ يبقى معروفاً كما كان. */
    public function test_the_gulf_still_reads_correctly()
    {
        foreach ([
            '96891234567' => 'OM',
            '+968 9123 4567' => 'OM',
            '00968 91234567' => 'OM',
            '971501234567' => 'AE',
            '966512345678' => 'SA',
            '+974 33123456' => 'QA',
            '96550012345' => 'KW',
            '97336001234' => 'BH',
        ] as $number => $iso) {
            $this->assertTrue(Phone::isValid($number), $number);
            $this->assertSame($iso, Phone::region($number), $number);
        }
    }

    public function test_country_code_with_wrong_local_length_is_rejected()
    {
        // عُمان ثمانية أرقام لا تسعة.
        $this->assertFalse(Phone::isValid('968912345678'));
        // والإمارات تسعة لا عشرة.
        $this->assertFalse(Phone::isValid('9715012345678'));
        // والهند عشرة لا أحد عشر.
        $this->assertFalse(Phone::isValid('+9112345678901'));
    }

    public function test_letters_are_never_a_phone_number()
    {
        $this->assertFalse(Phone::isValid('abc12345'));
        $this->assertFalse(Phone::isValid('اتصل بأخيه'));
    }

    public function test_too_short_and_too_long_are_rejected()
    {
        $this->assertFalse(Phone::isValid('9123'));
        $this->assertFalse(Phone::isValid(''));
        $this->assertFalse(Phone::isValid(null));
    }

    /**
     * ═══ الدولةُ المُسمّاةُ تُصدَّق ═══
     *
     * ‏«912345678901» بلا دولةٍ رقمٌ هنديٌّ ثابتٌ صحيح — ومن كتبه هكذا
     * يقصده. ومن اختار «عُمان» ثمّ كتبه إنّما أخطأ في رقمٍ عُمانيّ،
     * فقبولُه هندياً حفظُ رقمٍ لا يقصده أحدٌ ولا يُتّصل به.
     */
    public function test_a_named_country_is_believed_over_a_guess()
    {
        $this->assertTrue(Phone::isValid('912345678901'), 'بلا دولةٍ: مفتاحُه يقول الهند');
        $this->assertFalse(Phone::isValid('912345678901', 'OM'), 'قيل عُمان فقُبل هندياً');

        // ومن قال «السعوديّة» وكتب رقماً سعودياً بخانةٍ زائدةٍ يُردّ
        // ولا يُقرأ برازيلياً
        $this->assertFalse(Phone::isValid('5552000531', 'SA'));
    }

    public function test_separators_do_not_change_the_verdict()
    {
        foreach (['9123-4567', '9123 4567', '(9123) 4567', '+968-9123-4567'] as $written) {
            $this->assertTrue(Phone::isValid($written), $written);
            $this->assertSame('+96891234567', Phone::e164($written), $written);
        }
    }

    public function test_format_shows_the_code_apart_from_the_number()
    {
        $this->assertSame('+968 9123 4567', Phone::format('96891234567'));
        $this->assertSame('+968 9123 4567', Phone::format('91234567'));
        $this->assertSame('', Phone::format(null));
    }

    /**
     * ═══ الأطوالُ المسموحة لكلّ دولة ═══
     *
     * هي جوابُ السؤال الذي يطرحه الحقل: «كم رقماً؟». وتأتي من بيانات
     * ‏Google لا من جدولٍ عندنا، فلا تشيخ حين تغيّر دولةٌ خطّتَها.
     */
    public function test_each_country_knows_how_many_digits_it_takes()
    {
        $this->assertSame([8], Phone::lengths('OM'));
        $this->assertSame([9], Phone::lengths('SA'));
        // والإماراتُ تقبل ثمانيةً للثابت وتسعةً للمحمول — وصاحبُ
        // الشركة يعطي رقمَ مكتبه لا جوّاله
        $this->assertSame([8, 9], Phone::lengths('AE'));
        $this->assertSame([10], Phone::lengths('IN'));
        $this->assertSame([9, 10], Phone::lengths('GB'));

        // والسقفُ أوسعُ من طول المحمول: رقمٌ خدميٌّ أطولُ منه وهو صحيح
        $this->assertGreaterThanOrEqual(max(Phone::lengths('OM')), Phone::maxLength('OM'));
        $this->assertGreaterThanOrEqual(max(Phone::lengths('IN')), Phone::maxLength('IN'));
    }

    /** ورمزُ كلّ دولةٍ يعطي علمَها ومفتاحَها ومثالاً حيّاً على رقمها. */
    public function test_every_country_carries_a_flag_a_code_and_an_example()
    {
        $this->assertSame(968, Phone::dialCode('OM'));
        $this->assertSame(966, Phone::dialCode('sa'));
        $this->assertNull(Phone::dialCode('ZZ'));

        $this->assertSame('🇴🇲', Phone::flag('OM'));
        $this->assertSame('🇸🇦', Phone::flag('SA'));

        $this->assertTrue(Phone::isValid(Phone::example('OM'), 'OM'), 'مثالُ عُمان ليس رقماً صحيحاً فيها');
        $this->assertTrue(Phone::isValid(Phone::example('IN'), 'IN'), 'مثالُ الهند ليس رقماً صحيحاً فيها');
    }

    /**
     * والجزءُ المحلّيّ يُقتطع بمفتاح دولته لا بآخر ثمانية.
     *
     * وهو ما يُعرض مقنَّعاً في بوّابة الموكّلين — قصُّه من الوسط كان
     * يُري الموكّلَ شريحةً لا يعرفها من رقمه.
     */
    public function test_the_local_part_is_cut_at_the_country_code()
    {
        $this->assertSame('506233112', Phone::national('00971506233112'));
        $this->assertSame('91234567', Phone::national('96891234567'));
        $this->assertSame('552000531', Phone::national('966552000531'));

        // ورقمٌ محلّيٌّ بلا مفتاح يبقى كما هو: «71234567» العُمانيّ
        // يُفهم بـ«+7» رقماً روسياً ناقصاً — ولا يصحّ، فلا يُقصّ
        $this->assertSame('71234567', Phone::national('71234567'));
        $this->assertSame('91234567', Phone::national('91234567'));
        $this->assertSame('', Phone::national(''));
    }

    // ── الهاتف في نماذج النظام ────────────────────────────────────

    public function test_client_store_rejects_letters_in_phone()
    {
        $this->actingAs($this->developer())
            ->post('/clients', [
                'name' => 'سالم بن راشد',
                'phone' => 'اتصل بأخيه',
                'type' => 'individual',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_client_store_rejects_a_number_of_impossible_length()
    {
        $this->actingAs($this->developer())
            ->post('/clients', [
                'name' => 'سالم بن راشد',
                'phone' => '123',
                'type' => 'individual',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_client_store_accepts_a_gulf_number_with_or_without_code()
    {
        $developer = $this->developer();

        foreach (['91234567', '+968 9123 4567', '971501234567'] as $i => $phone) {
            $this->actingAs($developer)
                ->post('/clients', [
                    'name' => 'موكّل ' . $i,
                    'phone' => $phone,
                    'type' => 'individual',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('clients', 3);
    }

    public function test_phone_is_still_optional()
    {
        $this->actingAs($this->developer())
            ->post('/clients', [
                'name' => 'موكّل بلا هاتف',
                'type' => 'individual',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('clients', 1);
    }

    // ── البريد ───────────────────────────────────────────────────

    public function test_client_store_rejects_text_that_is_not_an_email()
    {
        $this->actingAs($this->developer())
            ->post('/clients', [
                'name' => 'سالم',
                'email' => 'ليس بريداً',
                'type' => 'individual',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_store_rejects_a_broken_email()
    {
        $this->actingAs($this->developer())
            ->post('/users', [
                'name' => 'موظف',
                'email' => 'employee@',
                'password' => 'Str0ng!Passw0rd#2026',
                'password_confirmation' => 'Str0ng!Passw0rd#2026',
                'role' => 'staff',
            ])
            ->assertSessionHasErrors('email');
    }

    // ── الاسم ────────────────────────────────────────────────────

    public function test_name_rejects_markup_and_control_characters()
    {
        $this->actingAs($this->developer())
            ->post('/clients', [
                'name' => '<script>alert(1)</script>',
                'type' => 'individual',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_name_accepts_arabic_and_latin_and_the_usual_punctuation()
    {
        $developer = $this->developer();

        foreach (['سالم بن راشد الرِّيامي', 'Salim R. Al-Riyami', 'مكتب (الريامي) للمحاماة'] as $i => $name) {
            $this->actingAs($developer)
                ->post('/clients', ['name' => $name, 'type' => 'individual'])
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('clients', 3);
    }

    // ── لا يضيع شغل سابق ─────────────────────────────────────────

    public function test_editing_an_old_client_keeps_working_when_its_phone_is_valid()
    {
        $client = Client::factory()->create(['phone' => '91234567']);

        $this->actingAs($this->developer())
            ->put("/clients/{$client->id}", [
                'name' => $client->name,
                'phone' => '91234567',
                'type' => $client->type ?? 'individual',
            ])
            ->assertSessionHasNoErrors();
    }
}
