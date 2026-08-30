<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * §10: الجدول المصدَّر يُقرأ فعلاً.
 *
 * كان الخط أبيض على الورقة كلها والتظليل على الصفوف الزوجية وحدها —
 * فالصف الفردي أبيضُ على أبيض، أي نصف الجدول غير مرئي. هذا الاختبار
 * يفتح الملف الناتج ويقارن لون الحبر بلون أرضيته صفاً صفاً.
 */
class ExportReadabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedCases(int $count = 5): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $client = Client::create(['name' => 'موكّل التصدير', 'phone' => '91234567', 'type' => 'individual']);

        for ($i = 0; $i < $count; $i++) {
            LegalCase::create([
                'client_id' => $client->id,
                'case_number' => 'X-' . $i,
                'title' => 'قضية رقم ' . $i,
                'type' => 'civil',
                'description' => 'وصف',
                'court' => 'الابتدائية',
                'opponent' => 'خصم',
                'status' => 'active',
                'priority' => 'medium',
            ]);
        }

        return $user;
    }

    /** @return array{ink: string, fill: string} */
    private function cellColors($sheet, string $cell): array
    {
        $style = $sheet->getStyle($cell);

        return [
            'ink' => strtoupper(substr((string) $style->getFont()->getColor()->getARGB(), -6)),
            'fill' => strtoupper(substr((string) $style->getFill()->getStartColor()->getARGB(), -6)),
        ];
    }

    public function test_no_row_is_written_in_ink_that_matches_its_background(): void
    {
        $user = $this->seedCases();

        $path = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        Excel::store(new \App\Exports\CasesExport($user), basename($path), 'local');
        $stored = storage_path('app/private/' . basename($path));
        if (! is_file($stored)) {
            $stored = storage_path('app/' . basename($path));
        }
        $this->assertFileExists($stored);

        $sheet = IOFactory::load($stored)->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $this->assertGreaterThan(5, $highestRow, 'الورقة تحمل صفوف البيانات');

        for ($row = 1; $row <= $highestRow; $row++) {
            $colors = $this->cellColors($sheet, "A{$row}");

            $this->assertNotSame(
                $colors['ink'],
                $colors['fill'],
                "الصف {$row}: الحبر بلون أرضيته — نصّ غير مرئي"
            );

            // ولا حبر أبيض على أرضية غير مصمتة (الافتراضي أبيض في Excel)
            if ($colors['ink'] === 'FFFFFF') {
                $this->assertNotContains(
                    $colors['fill'],
                    ['FFFFFF', '000000', ''],
                    "الصف {$row}: حبر أبيض بلا أرضية داكنة مضمونة"
                );
            }
        }

        @unlink($stored);
    }

    public function test_the_header_row_stays_branded_and_legible(): void
    {
        $user = $this->seedCases(2);

        $path = tempnam(sys_get_temp_dir(), 'hdr') . '.xlsx';
        Excel::store(new \App\Exports\CasesExport($user), basename($path), 'local');
        $stored = storage_path('app/private/' . basename($path));
        if (! is_file($stored)) {
            $stored = storage_path('app/' . basename($path));
        }

        $sheet = IOFactory::load($stored)->getActiveSheet();
        $header = $this->cellColors($sheet, 'A1');

        $this->assertSame('D4AF37', $header['fill'], 'الترويسة بذهبي الهوية');
        $this->assertSame('0A1628', $header['ink'], 'وحبرها كحليّ داكن يُقرأ عليه');
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());

        @unlink($stored);
    }
}
