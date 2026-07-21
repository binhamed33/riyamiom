<?php

namespace App\Exports;

use App\Models\Session;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SessionsExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Session::with('case')->get()->map(function ($session) {
            return [
                $session->case?->title ?? '',
                $session->date?->format('Y/m/d H:i') ?? '',
                $session->location ?? '',
                $session->status ?? '',
                $session->notes ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['القضية', 'التاريخ', 'الموقع', 'الحالة', 'ملاحظات'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 12]]];
    }
}
