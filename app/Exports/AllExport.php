<?php

namespace App\Exports;

use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\Client;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AllExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new CasesSheet(),
            new SessionsSheet(),
            new TasksSheet(),
            new ClientsSheet(),
        ];
    }
}

class CasesSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return LegalCase::with(['client', 'lawyer'])->get()->map(fn ($c) => [
            $c->case_number ?? '', $c->title ?? '', $c->client?->name ?? '',
            $c->lawyer?->name ?? '', $c->type ?? '', $c->court ?? '',
            $c->opponent ?? '', $c->status ?? '', $c->priority ?? '',
            $c->opened_at?->format('Y/m/d') ?? '', $c->next_date?->format('Y/m/d') ?? '',
            $c->notes ?? '',
        ]);
    }
    public function headings(): array { return ['رقم القضية', 'العنوان', 'العميل', 'المحامي', 'النوع', 'المحكمة', 'الخصم', 'الحالة', 'الأولوية', 'تاريخ الفتح', 'الموعد القادم', 'ملاحظات']; }
    public function title(): string { return 'القضايا'; }
    public function styles(Worksheet $sheet): array { return [1 => ['font' => ['bold' => true, 'size' => 12]]]; }
}

class SessionsSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Session::with('case')->get()->map(fn ($s) => [
            $s->case?->title ?? '', $s->date?->format('Y/m/d H:i') ?? '',
            $s->location ?? '', $s->status ?? '', $s->notes ?? '',
        ]);
    }
    public function headings(): array { return ['القضية', 'التاريخ', 'الموقع', 'الحالة', 'ملاحظات']; }
    public function title(): string { return 'الجلسات'; }
    public function styles(Worksheet $sheet): array { return [1 => ['font' => ['bold' => true, 'size' => 12]]]; }
}

class TasksSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Task::with(['assignee', 'case'])->get()->map(fn ($t) => [
            $t->title ?? '', $t->assignee?->name ?? '', $t->case?->title ?? '',
            $t->status ?? '', $t->priority ?? '',
            $t->due_date?->format('Y/m/d') ?? '', $t->completed_at?->format('Y/m/d') ?? '',
        ]);
    }
    public function headings(): array { return ['المهمة', 'المحامي المسؤول', 'القضية', 'الحالة', 'الأولوية', 'تاريخ الاستحقاق', 'تاريخ الإنجاز']; }
    public function title(): string { return 'المهام'; }
    public function styles(Worksheet $sheet): array { return [1 => ['font' => ['bold' => true, 'size' => 12]]]; }
}

class ClientsSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Client::with('cases')->get()->map(fn ($c) => [
            $c->name ?? '', $c->phone ?? '', $c->email ?? '',
            $c->address ?? '', $c->cases->count(), $c->notes ?? '',
        ]);
    }
    public function headings(): array { return ['الاسم', 'الهاتف', 'البريد الإلكتروني', 'العنوان', 'عدد القضايا', 'ملاحظات']; }
    public function title(): string { return 'العملاء'; }
    public function styles(Worksheet $sheet): array { return [1 => ['font' => ['bold' => true, 'size' => 12]]]; }
}
