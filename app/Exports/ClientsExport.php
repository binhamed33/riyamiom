<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientsExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
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
                $q->whereHas('cases', fn ($cq) => $cq->where('lawyer_id', $this->user->id))
                    ->orWhereDoesntHave('cases');
            });
        }
        return $query->get()->map(function ($client) {
            return [
                $client->name ?? '',
                $client->phone ?? '',
                $client->email ?? '',
                $client->address ?? '',
                $client->cases->count(),
                $client->notes ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['الاسم', 'الهاتف', 'البريد الإلكتروني', 'العنوان', 'عدد القضايا', 'ملاحظات'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 12]]];
    }
}
