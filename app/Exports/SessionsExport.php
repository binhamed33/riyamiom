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
    use \App\Exports\Concerns\StylesSheet;

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
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}
