<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Support\GulfPhone;
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

    public function test_local_number_without_country_code_is_accepted()
    {
        $this->assertTrue(GulfPhone::isValid('91234567'));
        $this->assertSame('محلي', GulfPhone::country('91234567'));
    }

    public function test_leading_zero_is_stripped_before_measuring()
    {
        $this->assertTrue(GulfPhone::isValid('0501234567'));
    }

    public function test_gulf_country_codes_are_recognised_by_name()
    {
        $this->assertSame('عُمان', GulfPhone::country('96891234567'));
        $this->assertSame('عُمان', GulfPhone::country('+968 9123 4567'));
        $this->assertSame('عُمان', GulfPhone::country('00968 91234567'));
        $this->assertSame('الإمارات', GulfPhone::country('971501234567'));
        $this->assertSame('السعودية', GulfPhone::country('966512345678'));
        $this->assertSame('قطر', GulfPhone::country('+974 33334444'));
        $this->assertSame('الكويت', GulfPhone::country('96512345678'));
        $this->assertSame('البحرين', GulfPhone::country('97333334444'));
    }

    public function test_country_code_with_wrong_local_length_is_rejected()
    {
        // عُمان ثمانية أرقام لا تسعة.
        $this->assertFalse(GulfPhone::isValid('968912345678'));
        // والإمارات تسعة لا عشرة.
        $this->assertFalse(GulfPhone::isValid('9715012345678'));

        // وما شابه المفتاح ولم يطابق طوله يُقاس رقماً محلياً: «97112345»
        // ثمانية أرقام تبدأ بتسعة — رقم عُماني محتمل، فلا يُردّ.
        $this->assertSame('محلي', GulfPhone::country('97112345'));
    }

    public function test_non_gulf_country_code_is_rejected()
    {
        $this->assertFalse(GulfPhone::isValid('+1 5551234567'));
        $this->assertFalse(GulfPhone::isValid('+44 7911123456'));
    }

    public function test_letters_are_never_a_phone_number()
    {
        $this->assertFalse(GulfPhone::isValid('abc12345'));
        $this->assertFalse(GulfPhone::isValid('اتصل بأخيه'));
    }

    public function test_too_short_and_too_long_are_rejected()
    {
        $this->assertFalse(GulfPhone::isValid('9123'));
        $this->assertFalse(GulfPhone::isValid('912345678901'));
        $this->assertFalse(GulfPhone::isValid(''));
        $this->assertFalse(GulfPhone::isValid(null));
    }

    public function test_separators_do_not_change_the_verdict()
    {
        foreach (['9123-4567', '9123 4567', '(9123) 4567', '+968-9123-4567'] as $written) {
            $this->assertTrue(GulfPhone::isValid($written), $written);
        }
    }

    public function test_format_shows_the_code_apart_from_the_number()
    {
        $this->assertSame('+968 9123 4567', GulfPhone::format('96891234567'));
        $this->assertSame('9123 4567', GulfPhone::format('91234567'));
        $this->assertSame('', GulfPhone::format(null));
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
