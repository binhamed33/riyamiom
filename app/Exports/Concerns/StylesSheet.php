<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * تنسيق موحّد لكل ورقة تُصدَّر.
 *
 * ═══ ما كان هنا وما كسره ═══
 *
 * كان الخط أبيض على الورقة كلها، والتظليل يقع على الصفوف الزوجية وحدها.
 * فالصف الزوجي أبيضُ على كحليّ داكن — مقروء، والفردي أبيضُ على أبيض —
 * غير مرئي إطلاقاً. نصف الجدول كان يختفي، وهو ما بدا «ألواناً مزعجة».
 *
 * القاعدة الآن: حبرٌ داكن دائماً على أرضية فاتحة، والتظليل تمييزٌ خفيف
 * لا لونٌ صارخ — فالورقة تُقرأ على الشاشة وتُطبع بالأبيض والأسود.
 */
trait StylesSheet
{
    /** ذهبي الهوية للترويسة، وحبرها كحليّ داكن — التباين عالٍ في الوضعين */
    private const HEADER_FILL = 'D4AF37';
    private const HEADER_INK = '0A1628';
    private const BODY_INK = '1F2937';
    private const ROW_ALT = 'FAF7F0';
    private const GRID = 'E5E1D8';

    protected function styleSheet(Worksheet $sheet): void
    {
        $highestCol = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        // الجدول كله: حبر داكن مقروء — يسبق أي تخصيص لاحق
        $full = "A1:{$highestCol}{$highestRow}";
        $sheet->getStyle($full)->getFont()->setSize(11)->getColor()->setARGB(self::BODY_INK);
        $sheet->getStyle($full)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($full)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GRID);

        $header = "A1:{$highestCol}1";
        $sheet->getStyle($header)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::HEADER_INK);
        $sheet->getStyle($header)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEADER_FILL);
        $sheet->getStyle($header)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);

        for ($row = 2; $row <= $highestRow; $row++) {
            $range = "A{$row}:{$highestCol}{$row}";
            if ($row % 2 === 0) {
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ROW_ALT);
            }
            $sheet->getStyle($range)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setWrapText(true);
        }

        if ($highestRow > 1) {
            $sheet->setAutoFilter("A1:{$highestCol}1");
        }

        foreach (range('A', $highestCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
