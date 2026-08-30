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
    use \App\Exports\Concerns\StylesSheet;

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
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}
