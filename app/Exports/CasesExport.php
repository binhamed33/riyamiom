<?php

namespace App\Exports;

use App\Models\LegalCase;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class CasesExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    use \App\Exports\Concerns\StylesSheet;

    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = LegalCase::with(['client', 'lawyer']);
        if ($this->user->isLawyer()) {
            $query->where('lawyer_id', $this->user->id);
        }
        return $query->get();
    }

    public function map($case): array
    {
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
    }

    public function headings(): array
    {
        return ['رقم القضية', 'العنوان', 'العميل', 'المحامي', 'النوع', 'المحكمة', 'الخصم', 'الحالة', 'الأولوية', 'تاريخ الفتح', 'الموعد القادم', 'ملاحظات'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}
