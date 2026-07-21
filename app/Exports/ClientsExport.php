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
    public function collection()
    {
        return Client::with('cases')->get()->map(function ($client) {
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
