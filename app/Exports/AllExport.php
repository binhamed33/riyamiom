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
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function sheets(): array
    {
        $user = $this->user;
        return [
            new CasesSheet($user),
            new SessionsSheet($user),
            new TasksSheet($user),
            new ClientsSheet($user),
        ];
    }
}

class CasesSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
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
        return $query->get()->map(fn ($c) => [
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
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = Session::with('case');
        if ($this->user->isLawyer()) {
            $query->whereHas('case', fn ($q) => $q->where('lawyer_id', $this->user->id));
        }
        return $query->get()->map(fn ($s) => [
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
    private $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function collection()
    {
        $query = Task::with(['assignee', 'case']);
        if ($this->user->isLawyer()) {
            $query->where('assigned_to', $this->user->id);
        }
        return $query->get()->map(fn ($t) => [
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
        return $query->get()->map(fn ($c) => [
            $c->name ?? '', $c->phone ?? '', $c->email ?? '',
            $c->address ?? '', $c->cases->count(), $c->notes ?? '',
        ]);
    }
    public function headings(): array { return ['الاسم', 'الهاتف', 'البريد الإلكتروني', 'العنوان', 'عدد القضايا', 'ملاحظات']; }
    public function title(): string { return 'العملاء'; }
    public function styles(Worksheet $sheet): array { return [1 => ['font' => ['bold' => true, 'size' => 12]]]; }
}
