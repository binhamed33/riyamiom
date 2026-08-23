<?php

namespace Tests\Feature;

use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * بيانات الخصم: تُكتب، تُقرأ، وتبقى القديمة كما هي.
 *
 * الفخّ الذي كاد يقع: الحقول الجديدة أُضيفت أول الأمر إلى $encryptable
 * وأعمدتها string(40). وقيمة «enc:» تتجاوز مئتي حرف، فالوضع الصارم في
 * MySQL يرفض الكتابة — والسطر يضيع. وقد وقع هذا في هذا الجدول نفسه من
 * قبل، ولذلك وُجدت هجرة increase_encrypted_columns_length_in_cases_table.
 */
class OpponentFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_new_opponent_field_exists(): void
    {
        foreach (['opponent_email', 'opponent_role', 'opponent_type', 'opponent_notes'] as $column) {
            $this->assertTrue(Schema::hasColumn('cases', $column), "العمود {$column} مفقود");
        }
    }

    public function test_the_encrypted_fields_survive_a_round_trip(): void
    {
        $case = LegalCase::factory()->create([
            'opponent' => 'شركة الخليج للتجارة',
            'opponent_email' => 'legal@gulf-trading.om',
            'opponent_notes' => 'رفض التسوية الودّية في الجلسة الأولى، ووكيله طلب مهلة أسبوعين.',
        ]);

        $fresh = LegalCase::findOrFail($case->id);

        $this->assertSame('legal@gulf-trading.om', $fresh->opponent_email);
        $this->assertStringContainsString('التسوية الودّية', $fresh->opponent_notes);

        // مخزَّنة مشفَّرة فعلاً لا نصّاً صريحاً
        $raw = DB::table('cases')->where('id', $case->id)->first();
        $this->assertStringStartsWith('enc:', $raw->opponent_email);
        $this->assertStringNotContainsString('gulf-trading', $raw->opponent_email);
    }

    public function test_the_categorical_fields_are_plain_so_they_can_be_filtered(): void
    {
        LegalCase::factory()->create(['opponent_type' => 'company', 'opponent_role' => 'defendant']);
        LegalCase::factory()->create(['opponent_type' => 'individual', 'opponent_role' => 'plaintiff']);

        // لو شُفِّرا لما نجح هذا الاستعلام إطلاقاً
        $this->assertSame(1, LegalCase::where('opponent_type', 'company')->count());
        $this->assertSame(1, LegalCase::where('opponent_role', 'plaintiff')->count());
    }

    public function test_existing_opponent_data_is_untouched_by_the_new_fields(): void
    {
        $case = LegalCase::factory()->create([
            'opponent' => 'مؤسسة النخيل',
            'opponent_phone' => '92345678',
            'opponent_address' => 'مسقط، الخوير',
            'opponent_lawyer' => 'المحامي سالم',
            'opponent_civil_number' => '12345678',
        ]);

        $fresh = LegalCase::findOrFail($case->id);

        $this->assertSame('مؤسسة النخيل', $fresh->opponent);
        $this->assertSame('92345678', $fresh->opponent_phone);
        $this->assertSame('مسقط، الخوير', $fresh->opponent_address);
        $this->assertSame('المحامي سالم', $fresh->opponent_lawyer);
        $this->assertSame('12345678', $fresh->opponent_civil_number);
        $this->assertNull($fresh->opponent_email);
    }

    public function test_the_fields_save_through_the_real_case_form(): void
    {
        // حقلٌ في القالب بلا تحقّق في المتحكّم واجهةٌ لا تحفظ. هذا
        // الاختبار يمرّ بالمسار الذي يمرّ به الموظّف: نموذج HTTP كامل.
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $client = \App\Models\Client::factory()->create();

        $this->actingAs($admin)->post(route('cases.store'), [
            'client_id' => $client->id,
            'case_number' => 'C-9001',
            'description' => 'وصف القضية',
            'court' => 'محكمة مسقط',
            'opponent' => 'شركة الخليج',
            'opponent_email' => 'legal@gulf.om',
            'opponent_role' => 'defendant',
            'opponent_type' => 'company',
            'opponent_notes' => 'رفض التسوية الودّية.',
            'status' => 'active',
            'priority' => 'medium',
        ])->assertSessionHasNoErrors();

        $case = LegalCase::where('case_number', 'C-9001')->firstOrFail();

        $this->assertSame('legal@gulf.om', $case->opponent_email);
        $this->assertSame('defendant', $case->opponent_role);
        $this->assertSame('company', $case->opponent_type);
        $this->assertStringContainsString('التسوية', $case->opponent_notes);
    }

    public function test_an_invented_opponent_type_is_refused(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $client = \App\Models\Client::factory()->create();

        $this->actingAs($admin)->post(route('cases.store'), [
            'client_id' => $client->id,
            'case_number' => 'C-9002',
            'description' => 'وصف',
            'court' => 'محكمة مسقط',
            'opponent' => 'خصم',
            'opponent_type' => 'شركه',  // خارج القائمة المغلقة
            'status' => 'active',
            'priority' => 'medium',
        ])->assertSessionHasErrors('opponent_type');

        $this->assertDatabaseMissing('cases', ['case_number' => 'C-9002']);
    }
}
