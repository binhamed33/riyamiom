<?php

namespace Tests\Feature;

use App\Services\PanelReporter;
use App\Support\StorageHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * القرصُ ومجلّداتُ storage يُفحصان قبل السجلّ — والنبضةُ تحملهما.
 *
 * امتلأ القرصُ فسقطت صفحاتُ المكتب بيضاء، وفحصُ الصحّة قال «لا أخطاء»
 * لأنّه يقرأ سجلاً لم يُكتب، والنبضةُ بلّغت صفرَ أخطاءٍ للسبب نفسِه.
 */
class StorageHealthTest extends TestCase
{
    use RefreshDatabase;

    private const MIB = 1024 * 1024;

    private const GIB = 1024 * 1024 * 1024;

    /** مجلّدٌ مفقودٌ يُسمّى باسمه المختصر — لا يُعدّ سليماً لأنّه غيرُ موجود. */
    public function test_a_missing_directory_is_reported_by_its_short_name(): void
    {
        $bad = StorageHealth::unwritable([
            'storage/framework/views' => sys_get_temp_dir() . '/missing-' . uniqid(),
            'storage/logs' => sys_get_temp_dir(),
        ]);

        $this->assertSame(['storage/framework/views'], array_keys($bad));
    }

    public function test_writable_directories_pass(): void
    {
        $this->assertSame([], StorageHealth::unwritable(['storage/logs' => sys_get_temp_dir()]));
    }

    /** الحرجُ بالمطلق وحدَه (كما في سكربت النشر)، والضيقُ بالمطلق أو النسبة. */
    public function test_critical_is_absolute_and_tight_is_absolute_or_relative(): void
    {
        $this->assertTrue(StorageHealth::judge(100 * self::MIB, 100 * self::GIB), 'دون 512 م.ب حرج');
        $this->assertFalse(StorageHealth::judge(3 * self::GIB, 100 * self::GIB), '3٪ من قرصٍ كبير غيغاباياتٌ — ليس حرجاً');
        $this->assertFalse(StorageHealth::judge(10 * self::GIB, 100 * self::GIB));
        $this->assertNull(StorageHealth::judge(null, null), 'ما لم يُقرأ لا يُحكم عليه');

        $this->assertTrue(StorageHealth::tight(3 * self::GIB, 100 * self::GIB), '3٪ يضيق');
        $this->assertTrue(StorageHealth::tight(1 * self::GIB, null), 'دون 2 غ.ب يضيق ولو جُهل الكلّيّ');
        $this->assertFalse(StorageHealth::tight(10 * self::GIB, 100 * self::GIB));
        $this->assertFalse(StorageHealth::tight(null, null));
    }

    public function test_human_sizes(): void
    {
        $this->assertSame('512 م.ب', StorageHealth::human(512 * self::MIB));
        $this->assertSame('1.5 غ.ب', StorageHealth::human((int) (1.5 * self::GIB)));
    }

    /** فحصُ الصحّة يبدأ بالقرص ويقول كم حرٌّ عليه. */
    public function test_the_health_command_reports_the_disk_first(): void
    {
        config(['app.url' => 'https://office.example.test']);
        Http::fake(['*' => Http::response(str_repeat('x', 200_000), 200)]);

        $this->artisan('office:health')
            ->expectsOutputToContain('القرص والتخزين')
            ->expectsOutputToContain('حرٌّ على القرص');
    }

    /** النبضةُ تحمل القرصَ: حرٌّ وكلّيٌّ وهل تُكتب storage/ — أرقامٌ بلا مسارات. */
    public function test_the_heartbeat_carries_the_disk_block(): void
    {
        config(['panel.ingest_url' => 'https://panel.example.test', 'panel.ingest_token' => 'tok']);
        Http::fake(['panel.example.test/*' => Http::response(['ok' => true], 200)]);

        PanelReporter::heartbeat();

        Http::assertSent(function ($request) {
            $disk = $request['disk'] ?? null;

            return is_array($disk)
                && is_int($disk['free_bytes'])
                && is_int($disk['total_bytes'])
                && is_bool($disk['writable'])
                && !array_key_exists('path', $disk);
        });
    }
}
