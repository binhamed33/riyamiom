<?php

namespace App\Exports;

use App\Models\LegalCase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CasesExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return LegalCase::with(['client', 'lawyer'])->get()->map(function ($case) {
            return [
                $case->case_number ?? '',
                $case->title ?? '',
                $case->client?->name ?? '',
                $case->lawyer?->name ?? '',
                $case->type ?? '',
                $case->court ?? '',
                $case->opponent ?? '',
                $case->status ?? '',
                $case->priority ?? '',
                $case->opened_at?->format('Y/m/d') ?? '',
                $case->next_date?->format('Y/m/d') ?? '',
                $case->notes ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['رقم القضية', 'العنوان', 'العميل', 'المحامي', 'النوع', 'المحكمة', 'الخصم', 'الحالة', 'الأولوية', 'تاريخ الفتح', 'الموعد القادم', 'ملاحظات'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 12]]];
    }
}
