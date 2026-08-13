<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ClientsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = Client::with('cases');
        if ($this->user->isLawyer()) {
            $query->where(function ($q) {
                $q->whereHas('cases', fn($cq) => $cq->where('lawyer_id', $this->user->id))
                    ->orWhereDoesntHave('cases');
            });
        }
        return $query->get();
    }

    public function map($client): array
    {
        return [
            $client->name ?? '',
            $client->phone ?? '',
            $client->email ?? '',
            $client->address ?? '',
            $client->cases->count(),
            $client->cases->where('status', 'active')->count(),
            $client->notes ?? '',
        ];
    }

    public function headings(): array
    {
        return ['الاسم', 'الهاتف', 'البريد الإلكتروني', 'العنوان', 'عدد القضايا', 'القضايا النشطة', 'ملاحظات'];
    }

    public function registerEvents(): array
    {
        $gold = 'D4AF37';
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
