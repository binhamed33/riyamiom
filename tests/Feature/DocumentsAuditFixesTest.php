<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseFolder;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * عيوبُ صفحة المستندات التي رصدها تدقيقُ البروفة.
 *
 * ═══ ما وقع ═══
 *
 * ‏A1  لا يُشارَك مستندٌ مع الموكّل: لا زرَّ ولا خانة، والمسارُ يسقط بـ500.
 * ‏A3  «.Document uploaded successfully» في شريطٍ عربيّ.
 * ‏A4  «14 مستنداً» فوق قائمةٍ من ثلاثة عشر — الرابعَ عشرَ خاصٌّ بزميلة.
 * ‏A6  «مصغّرات» بخمس عشرة بطاقة تكتب خمسةَ عشرَ سطرَ «معاينة» — وأوّلُ علاجه
 *      (‎?thumb=1‎ يُسقط السطر) فتح طريقاً لقراءة المستند بلا أثر.
 * ‏A7  بحثُ Ctrl+K لا يُرجع شيئاً متى طابق مستندٌ بلا قضية.
 * ‏A11 «KB 65.2» — الوحدةُ اللاتينيّة تتقدّم الرقم.
 * ‏A14 عمودُ الإجراءات يخرج عن شاشة 1440.
 * ‏A15 «7 مستند» و«ونُقل 3 مستنداً».
 * ‏A16 «الكل | الجميع (1)».
 * ‏A17 معاينةُ ملفٍّ مفقود ترسم التطبيقَ كلَّه داخل العارض.
 */
class DocumentsAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $maryam;
    private Client $client;
    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        Setting::set('subscription_status', 'active', 'subscription');
        Setting::set('subscription_start_at', now()->subMonth()->toDateString(), 'subscription');
        Setting::set('subscription_end_at', now()->addYear()->toDateString(), 'subscription');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'ar']);
        $this->maryam = User::factory()->create(['role' => 'lawyer', 'is_active' => true, 'locale' => 'ar', 'name' => 'مريم']);

        $this->client = Client::create([
            'name' => 'شركة الواحة الخضراء للتجارة', 'type' => 'company',
            'national_id' => (string) random_int(1000000, 9999999), 'phone' => '96890000000',
        ]);

        $this->case = LegalCase::create([
            'case_number' => '2026/688', 'office_case_number' => '688', 'title' => 'مطالبة توريد',
            'description' => 'و', 'type' => 'تجاري', 'court' => 'المحكمة الابتدائية', 'opponent' => 'خصم',
            'status' => 'active', 'priority' => 'medium',
            'client_id' => $this->client->id, 'lawyer_id' => $this->maryam->id,
            'created_by' => $this->admin->id, 'opened_at' => now(),
        ]);
    }

    private function doc(array $over = []): Document
    {
        static $n = 0;
        $n++;

        return Document::create(array_merge([
            'case_id' => $this->case->id, 'uploaded_by' => $this->admin->id,
            'title' => 'مستند ' . $n, 'file_path' => 'documents/doc-' . $n . '.pdf',
            'file_type' => 'pdf', 'file_size' => 66765, 'access_level' => 'all',
        ], $over));
    }

    // ══════════ A1: المشاركةُ مع الموكّل ══════════

    public function test_the_share_toggle_flips_the_flag_and_answers_in_arabic(): void
    {
        $doc = $this->doc();

        $this->actingAs($this->admin)->from(route('documents.index'))
            ->post(route('documents.clientVisibility', $doc))
            ->assertRedirect(route('documents.index'))
            ->assertSessionHas('success', 'صار المستند مرئياً للعميل في بوابته.');
        $this->assertTrue((bool) $doc->fresh()->client_visible);

        $row = AuditLog::where('model_type', Document::class)->where('model_id', $doc->id)->latest('id')->first();
        $this->assertSame(['client_visible' => false], array_intersect_key($row->old_values, ['client_visible' => 1]));
        $this->assertSame(['client_visible' => true], array_intersect_key($row->new_values, ['client_visible' => 1]));

        $this->actingAs($this->admin)->from(route('documents.index'))
            ->post(route('documents.clientVisibility', $doc))
            ->assertSessionHas('success', 'أُخفي المستند عن بوابة العميل.');
        $this->assertFalse((bool) $doc->fresh()->client_visible);
    }

    public function test_upload_with_client_visible_stores_it_and_private_never_does(): void
    {
        $this->actingAs($this->admin)->post(route('documents.store'), [
            'case_id' => $this->case->id, 'title' => 'صحيفة الدعوى', 'access_level' => 'all',
            'client_visible' => '1', 'file' => UploadedFile::fake()->create('claim.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));

        $this->assertTrue((bool) Document::where('title', 'صحيفة الدعوى')->value('client_visible'));

        $this->actingAs($this->admin)->post(route('documents.store'), [
            'case_id' => $this->case->id, 'title' => 'مسوّدة خاصة', 'access_level' => 'private',
            'client_visible' => '1', 'file' => UploadedFile::fake()->create('draft.pdf', 40, 'application/pdf'),
        ]);

        $this->assertFalse((bool) Document::where('title', 'مسوّدة خاصة')->value('client_visible'));
    }

    public function test_the_upload_modal_and_the_rows_carry_the_share_controls(): void
    {
        $shareable = $this->doc(['title' => 'عقد التوريد']);
        $private = $this->doc(['title' => 'ملاحظة خاصة', 'access_level' => 'private']);

        $html = $this->actingAs($this->admin)->get(route('documents.index', ['all' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('name="client_visible" value="0"', $html, 'النموذجُ لا يرسل client_visible');
        $this->assertStringContainsString('name="client_visible" value="1"', $html);
        $this->assertStringContainsString(route('documents.clientVisibility', $shareable), $html, 'لا زرَّ مشاركةٍ في الجدول');
        $this->assertStringNotContainsString(route('documents.clientVisibility', $private), $html, 'الخاصُّ لا يُشارَك');

        $case = $this->actingAs($this->admin)->get(route('cases.show', $this->case))->assertOk()->getContent();
        $this->assertStringContainsString(route('documents.clientVisibility', $shareable), $case, 'لا زرَّ مشاركةٍ في تبويب مستندات القضية');
    }

    /**
     * قاعدةُ الإتاحة واحدةٌ في الرفع والزرّ والمسار (Document::canBeSharedBy).
     *
     * كان الرفعُ يقبل ‎client_visible=1‎ لكلّ صلاحيةٍ عدا الخاصّ، والزرُّ والمسارُ
     * يمنعان الموظّفَ في «الفريق»: موظّفٌ يرفع من «مستند» السريع في صفحة القضية
     * (وهو «فريق» دائماً) ويعلّم «مرئي للموكّل» فيصل المستندُ إلى البوابة، ثم لا
     * يجد زرّاً يتراجع به ويردّ المسارُ ٤٠٣.
     */
    public function test_staff_cannot_share_a_team_document_by_uploading_it(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true, 'locale' => 'ar']);

        // كما يرسله الرفعُ السريع في صفحة القضية
        $this->actingAs($staff)->post(route('documents.store'), [
            'case_id' => $this->case->id, 'title' => 'محضر الفريق', 'access_level' => 'team',
            'client_visible' => '1', 'file' => UploadedFile::fake()->create('team.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('documents.index'));
        $this->assertFalse((bool) Document::where('title', 'محضر الفريق')->value('client_visible'), 'الموظّفُ أتاح مستندَ «الفريق» بالرفع');

        // و«الجميع» يُتيحه — كما يُتيحه بالزرّ
        $this->actingAs($staff)->post(route('documents.store'), [
            'case_id' => $this->case->id, 'title' => 'إيصال عام', 'access_level' => 'all',
            'client_visible' => '1', 'file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ]);
        $this->assertTrue((bool) Document::where('title', 'إيصال عام')->value('client_visible'));

        // والمحامي يُتيح «الفريق» بالرفع كما بالزرّ
        $this->actingAs($this->maryam)->post(route('documents.store'), [
            'case_id' => $this->case->id, 'title' => 'مذكرة المحامية', 'access_level' => 'team',
            'client_visible' => '1', 'file' => UploadedFile::fake()->create('memo.pdf', 20, 'application/pdf'),
        ]);
        $this->assertTrue((bool) Document::where('title', 'مذكرة المحامية')->value('client_visible'));

        // والمسارُ على القاعدة نفسِها
        $team = Document::where('title', 'محضر الفريق')->firstOrFail();
        // ‏abort(403) يعود إلى اللوحة برسالة (bootstrap/app.php) — والعلَمُ لا يُقلب
        $this->actingAs($staff)->post(route('documents.clientVisibility', $team))->assertRedirect(route('dashboard'));
        $this->assertFalse((bool) $team->fresh()->client_visible);
        $this->assertFalse(Document::canBeSharedBy($staff, Document::ACCESS_TEAM));
        $this->assertTrue(Document::canBeSharedBy($staff, Document::ACCESS_ALL));
        $this->assertTrue(Document::canBeSharedBy($staff, null), 'المستندُ القديمُ بلا صلاحيةٍ «للجميع»');
        $this->assertFalse(Document::canBeSharedBy($this->admin, Document::ACCESS_PRIVATE));
    }

    public function test_the_forms_do_not_offer_staff_a_team_share(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true, 'locale' => 'ar']);

        // نافذةُ الرفع: الخانةُ تُعطَّل مع «الفريق» ويُقال لماذا — للموظّف وحده
        $this->actingAs($staff)->get(route('documents.index'))->assertOk()->assertSee('data-team-share-hint', false);
        $this->actingAs($this->maryam)->get(route('documents.index'))->assertOk()->assertDontSee('data-team-share-hint', false);

        // والرفعُ السريع في صفحة القضية «فريق» دائماً: لا خانةَ للموظّف
        $this->actingAs($this->maryam)->get(route('cases.show', $this->case))->assertOk()->assertSee('data-quick-client-visible', false);
        $this->actingAs($staff)->get(route('cases.show', $this->case))->assertOk()->assertDontSee('data-quick-client-visible', false);
    }

    // ══════════ A3: رسائلُ عربيّة ══════════

    public function test_upload_and_delete_messages_are_arabic(): void
    {
        $this->actingAs($this->admin)->post(route('documents.store'), [
            'case_id' => $this->case->id, 'title' => 'وكالة', 'access_level' => 'all',
            'file' => UploadedFile::fake()->create('poa.pdf', 20, 'application/pdf'),
        ])->assertSessionHas('success', 'رُفع المستند.');

        $doc = Document::where('title', 'وكالة')->firstOrFail();
        $this->actingAs($this->admin)->delete(route('documents.destroy', $doc))
            ->assertSessionHas('success', 'حُذف المستند.');
    }

    // ══════════ A4: الأعدادُ بصلاحية القارئ ══════════

    public function test_another_users_private_document_is_not_counted_anywhere(): void
    {
        $folder = CaseFolder::create(['case_id' => $this->case->id, 'name' => 'مسوّدات', 'sort' => 1]);
        $this->doc(['title' => 'صحيفة']);
        $this->doc(['title' => 'مذكّرة']);
        // خاصٌّ بمريم داخل «مسوّدات» — المديرُ لا يراه ولا يُعدّ له
        $this->doc(['title' => 'مسوّدةُ مريم', 'access_level' => 'private', 'uploaded_by' => $this->maryam->id, 'case_folder_id' => $folder->id]);

        $root = $this->actingAs($this->admin)->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('مستندات (شركة الواحة الخضراء للتجارة)', $root);
        $this->assertStringContainsString('مستندان', $root, 'بطاقةُ الموكّل تعدّ الخاصَّ بغيره');
        $this->assertStringNotContainsString('3 مستندات', $root);

        $client = $this->actingAs($this->admin)->get(route('documents.index', ['client_id' => $this->client->id]))->getContent();
        $this->assertMatchesRegularExpression('/2026\/688\s*<span class="text-gray-400">\(2\)<\/span>/u', $client);

        $inCase = $this->actingAs($this->admin)->get(route('documents.index', ['case_id' => $this->case->id]))->getContent();
        $this->assertMatchesRegularExpression('/📁 مسوّدات\s*<span class="text-gray-400">\(0\)<\/span>/u', $inCase);

        $case = $this->actingAs($this->admin)->get(route('cases.show', $this->case))->getContent();
        $this->assertStringContainsString('مسوّدات (0)', $case, 'شريحةُ المجلد في صفحة القضية تعدّ الخاصَّ بغيره');
        $this->assertStringNotContainsString('مسوّدةُ مريم', $case);

        // وصاحبتُه تراه وتُعدّ له
        $own = $this->actingAs($this->maryam)->get(route('cases.show', $this->case))->getContent();
        $this->assertStringContainsString('مسوّدات (1)', $own);
    }

    // ══════════ A6: كلُّ وصولٍ يُدوَّن، والتكرارُ يُطوى ══════════

    /**
     * ‏?thumb=1 كان يُسقط سطرَ «معاينة» والمسارُ يردّ الملفَّ كاملاً: من كتبه
     * بيده قرأ المستندَ بلا أثر. فالسطرُ يُكتب أبداً، مرّةً لكلّ مستخدمٍ ومستندٍ
     * في عشر دقائق (Cache::add). ومخزنُ الاختبار ‏array‏ يبقى عبر طلبات الاختبار
     * الواحد، فالطيُّ يُرى هنا كما في الإنتاج.
     */
    public function test_every_preview_is_audited_once_per_user_and_document_per_ten_minutes(): void
    {
        $doc = $this->doc();
        Storage::disk('private')->put($doc->file_path, '%PDF-1.4 test');
        $previews = fn () => AuditLog::where('action', 'preview')->where('model_id', $doc->id)->count();

        $html = $this->actingAs($this->admin)->get(route('documents.index', ['all' => 1]))->getContent();
        $this->assertStringNotContainsString('thumb=1', $html, 'معامِلٌ لا يفعل شيئاً بقي في الرابط');

        $this->actingAs($this->admin)->get(route('documents.preview', [$doc, 'thumb' => 1]))->assertOk();
        $this->assertSame(1, $previews(), '‏?thumb=1 ما زال يُسقط سطرَ التدقيق');

        // إعادةُ صفحة المصغّرات ثم فتحُ العارض في الدقائق نفسِها: سطرٌ واحد
        $this->actingAs($this->admin)->get(route('documents.preview', $doc))->assertOk();
        $this->actingAs($this->admin)->get(route('documents.preview', $doc))->assertOk();
        $this->assertSame(1, $previews(), 'التكرارُ لم يُطوَ');

        // ومستخدمٌ آخر سطرُه له
        $this->actingAs($this->maryam)->get(route('documents.preview', $doc))->assertOk();
        $this->assertSame(2, $previews());

        // وبعد عشر دقائق وصولٌ جديد
        $this->travel(11)->minutes();
        $this->actingAs($this->admin)->get(route('documents.preview', $doc))->assertOk();
        $this->assertSame(3, $previews());
    }

    // ══════════ A7: بحثُ Ctrl+K ══════════

    public function test_command_search_survives_a_document_without_a_case(): void
    {
        $this->doc(['case_id' => null, 'title' => 'اتفاقية تعاون']);
        $this->doc(['title' => 'اتفاقية توريد']);

        $response = $this->actingAs($this->admin)->getJson('/command?q=اتفاقية')->assertOk();

        $docs = collect($response->json('groups.document'));
        $this->assertCount(2, $docs, 'البحثُ أُفرغ كلُّه بمستندٍ بلا قضية');
        $urls = $docs->pluck('url', 'label');
        $this->assertSame(route('cases.show', $this->case) . '#documents', $urls['اتفاقية توريد']);
        $this->assertStringStartsWith(route('documents.index'), $urls['اتفاقية تعاون']);
    }

    // ══════════ A11 · A14 · A15 · A16: ما يُقرأ في الجدول ══════════

    public function test_the_table_reads_arabic_sizes_and_fits_its_columns(): void
    {
        $this->doc(['file_size' => 66765]);

        $html = $this->actingAs($this->admin)->get(route('documents.index', ['all' => 1]))->getContent();

        $this->assertStringContainsString('<bdi>65 ك.ب</bdi>', $html);
        $this->assertDoesNotMatchRegularExpression('/\d\s*KB/', $html, 'الحجمُ ما زال «KB»');
        $this->assertStringContainsString('max-w-[9rem] truncate" title="مطالبة توريد"', $html, 'عنوانُ القضية لا يُقصّ فيمدّ الجدول');
        $this->assertStringContainsString('font-semibold whitespace-nowrap">' . __('app.actions'), $html);
    }

    public function test_document_counts_agree_with_their_noun(): void
    {
        foreach (range(1, 7) as $i) {
            $this->doc();
        }

        $root = $this->actingAs($this->admin)->get(route('documents.index'))->getContent();
        $this->assertStringContainsString('7 مستندات', $root);
        $this->assertStringNotContainsString('7 مستند<', $root);

        $folder = CaseFolder::create(['case_id' => $this->case->id, 'name' => 'مرفقات', 'sort' => 1]);
        Document::where('case_id', $this->case->id)->limit(3)->update(['case_folder_id' => $folder->id]);

        $this->actingAs($this->admin)->delete(route('case-folders.destroy', $folder))
            ->assertSessionHas('success', 'حُذف المجلد ونُقل 3 مستندات إلى «عام».');
    }

    public function test_the_unfiled_chip_reads_general_not_everyone(): void
    {
        $this->doc();

        $html = $this->actingAs($this->admin)->get(route('documents.index', ['case_id' => $this->case->id]))->getContent();

        $this->assertMatchesRegularExpression('/>\s*عام \(1\)\s*</u', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*' . preg_quote(__('app.general'), '/') . ' \(\d+\)/u', $html);
    }

    /**
     * والواجهةُ الإنجليزيّة إنجليزيّة: كانت الشريحةُ «عام» مكتوبةً في القالب،
     * والحجمُ «65 ك.ب» أيّاً كانت اللغة — في جدولٍ إنجليزيٍّ من اليسار.
     */
    public function test_an_english_user_reads_an_english_chip_and_size_units(): void
    {
        $english = User::factory()->create(['role' => 'admin', 'is_active' => true, 'locale' => 'en']);
        $this->doc(['file_size' => 66765]);

        $chip = $this->actingAs($english)->get(route('documents.index', ['case_id' => $this->case->id]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>\s*General \(1\)\s*</u', $chip);
        $this->assertDoesNotMatchRegularExpression('/>\s*عام \(\d+\)/u', $chip);

        $table = $this->actingAs($english)->get(route('documents.index', ['all' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('<bdi>65 KB</bdi>', $table);
        $this->assertStringNotContainsString('ك.ب', $table);
    }

    // ══════════ A17: معاينةُ ملفٍّ مفقود ══════════

    public function test_previewing_a_missing_file_is_a_small_arabic_page_not_the_app(): void
    {
        $doc = $this->doc(['title' => 'حكم ابتدائي']);

        $response = $this->actingAs($this->admin)->get(route('documents.preview', $doc));

        $response->assertNotFound();
        $html = $response->getContent();
        $this->assertStringContainsString('data-document-unavailable', $html);
        $this->assertStringContainsString('حكم ابتدائي', $html);
        $this->assertStringNotContainsString('<aside', $html, 'التطبيقُ كلُّه رُسم داخل العارض');
        // الملفُّ هو المفقود: «ارفعه» كما في الإنجليزيّة، لا «نزّله» فيُردّ «غير موجود»
        $this->assertStringContainsString('ارفعه من جديد', $html);
        $this->assertStringNotContainsString('نزّله', $html);

        // والتنزيلُ يبقى تحويلاً بإشعار — صفحةٌ كاملةٌ لا إطار
        $this->actingAs($this->admin)->get(route('documents.download', $doc))
            ->assertRedirect(route('documents.index'))->assertSessionHas('error');
    }
}
