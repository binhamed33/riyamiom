<?php

namespace App\Exports;

use App\Models\Session;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SessionsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = Session::with('case');
        if ($this->user->isLawyer()) {
            $query->whereHas('case', fn($q) => $q->where('lawyer_id', $this->user->id));
        }
        return $query->get();
    }

    public function map($session): array
    {
        return [
            $session->case?->case_number ?? '',
            $session->case?->title ?? '',
            $session->date?->format('Y/m/d H:i') ?? '',
            $session->location ?? '',
            $session->status ?? '',
            $session->notes ?? '',
        ];
    }

    public function headings(): array
    {
        return ['رقم القضية', 'القضية', 'التاريخ', 'الموقع', 'الحالة', 'ملاحظات'];
    }

    public function registerEvents(): array
    {
        $gold = 'B89B5E';
        $navy = '0A1628';
        $altRow = '1A2D4A';
        $white = 'FFFFFF';

        return [
            AfterSheet::class => function (AfterSheet $event) use ($gold, $navy, $altRow, $white) {
                $sheet = $event->sheet->getDelegate();
                $highestCol = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                $sheet->setRightToLeft(true);
                $sheet->freezePane('A2');

                $headerRange = "A1:{$highestCol}1";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB($white);
                $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($gold);
                $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->getStyle($headerRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($navy);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $range = "A{$row}:{$highestCol}{$row}";
                    if ($row % 2 === 0) {
                        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($altRow);
                    }
                    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('2A3D5A');
                }

                $fullRange = "A1:{$highestCol}{$highestRow}";
                $sheet->getStyle($fullRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB($gold);
                $sheet->getStyle($fullRange)->getFont()->setSize(11)->getColor()->setARGB($white);

                foreach (range('A', $highestCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
