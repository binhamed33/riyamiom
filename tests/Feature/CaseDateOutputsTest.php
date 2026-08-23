<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مخرجات القضية التي تعتمد على التواريخ.
 *
 * كان casts() في LegalCase فارغاً، فـ opened_at وnext_date نصّان لا
 * تاريخان. وستّة مواضع في الشيفرة تناديهما بـ ?->format() — فتنهار على
 * نصّ. النتيجة: ملف القضية PDF، وتصدير القضايا، وملخّص القضية، كلّها
 * تسقط لأي قضية لها تاريخ فتح — وتاريخ الفتح يُملأ تلقائياً عند الإنشاء،
 * أي لكل قضية.
 *
 * هذه الاختبارات تُشغّل المخرجات الثلاثة فعلاً، لا تفحص الإعداد.
 */
class CaseDateOutputsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'developer', 'is_active' => true]);
    }

    private function caseWithDates(): LegalCase
    {
        $case = LegalCase::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'opened_at' => '2026-03-01',
            'next_date' => '2026-09-15',
        ]);

        Session::factory()->create(['case_id' => $case->id]);
        Task::factory()->create(['case_id' => $case->id]);

        return $case;
    }

    public function test_the_dates_come_back_as_dates_not_strings(): void
    {
        $case = $this->caseWithDates()->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $case->opened_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $case->next_date);
        $this->assertSame('2026-03-01', $case->opened_at->format('Y-m-d'));
        $this->assertSame('2026-09-15', $case->next_date->format('Y-m-d'));
    }

    public function test_a_case_with_no_next_date_stays_null(): void
    {
        $case = LegalCase::factory()->create([
            'client_id' => Client::factory()->create()->id,
            'next_date' => null,
        ]);

        $this->assertNull($case->fresh()->next_date);
    }

    public function test_the_case_summary_endpoint_answers_instead_of_crashing(): void
    {
        $case = $this->caseWithDates();

        $this->actingAs($this->staff())
            ->get("/cases/{$case->id}/summarize")
            ->assertOk()
            ->assertJsonPath('opened_at', '2026-03-01')
            ->assertJsonStructure(['id', 'case_number', 'title', 'status', 'priority', 'client', 'lawyer', 'opened_at']);
    }

    public function test_the_case_file_pdf_view_renders(): void
    {
        $case = $this->caseWithDates()->load(['client', 'lawyer', 'sessions', 'tasks', 'documents']);

        $html = view('pdf.case-file', ['case' => $case, 'title' => 'ملف القضية'])->render();

        $this->assertStringContainsString('2026/03/01', $html);
        $this->assertStringContainsString('2026/09/15', $html);
    }

    public function test_the_cases_export_maps_every_row_without_crashing(): void
    {
        $this->caseWithDates();
        $user = $this->staff();

        $export = new \App\Exports\CasesExport($user);

        // map() هو ما ينهار: يستدعي ?->format() على التاريخين
        foreach ($export->collection() as $case) {
            $row = $export->map($case);

            $this->assertContains('2026/03/01', $row);
            $this->assertContains('2026/09/15', $row);
        }
    }

    public function test_the_full_export_maps_cases_without_crashing(): void
    {
        $this->caseWithDates();
        $user = $this->staff();

        $export = new \App\Exports\AllExport($user);

        // AllExport يبني ورقة القضايا بنفس النداء
        $this->assertNotEmpty($export->sheets());
    }

    public function test_a_signed_in_user_can_download_the_cases_export(): void
    {
        $this->caseWithDates();

        $response = $this->actingAs($this->staff())->get(route('export.cases'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            (string) $response->headers->get('content-type'),
        );
    }



    /**
     * هذان الاختباران يكتبان تاريخاً فاسداً عمداً ليُثبتا أنّ الصفحة
     * تصمد أمامه. وMySQL في وضعها الصارم ترفض الكتابة أصلاً، فلا
     * يستطيع الاختبار أن يبني حالته.
     *
     * الدفاع في الكود يبقى لازماً: قاعدة مكتبٍ قديمة أُنشئت قبل الوضع
     * الصارم قد تحمل «0000-00-00» فعلاً، وهي تُقرأ ولا تُكتب.
     */
    private function skipOnStrictEngine(): void
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('محرّك صارم يرفض كتابة تاريخ فاسد — الحالة لا تُبنى إلا على SQLite');
        }
    }
    public function test_a_legacy_zero_date_reads_as_empty_instead_of_crashing(): void
    {
        $this->skipOnStrictEngine();

        $case = $this->caseWithDates();

        // قاعدة قديمة بلا وضع صارم قد تحمل هذه القيمة في عمود تاريخ
        \Illuminate\Support\Facades\DB::table('cases')
            ->where('id', $case->id)
            ->update(['next_date' => '0000-00-00']);

        $fresh = $case->fresh();

        $this->assertNull($fresh->next_date);

        // والأهم: الصفحات التي تعتمد عليه تبقى تعمل
        $this->actingAs($this->staff())
            ->get("/cases/{$case->id}/summarize")
            ->assertOk()
            ->assertJsonPath('next_date', null);
    }

    public function test_an_unreadable_date_does_not_break_the_case_page(): void
    {
        $this->skipOnStrictEngine();

        $case = $this->caseWithDates();

        \Illuminate\Support\Facades\DB::table('cases')
            ->where('id', $case->id)
            ->update(['opened_at' => 'ليس تاريخاً']);

        $this->assertNull($case->fresh()->opened_at);

        $html = view('pdf.case-file', [
            'case' => $case->fresh()->load(['client', 'lawyer', 'sessions', 'tasks', 'documents']),
            'title' => 'ملف القضية',
        ])->render();

        $this->assertNotEmpty($html);
    }
}
