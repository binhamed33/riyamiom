<?php

namespace App\Exports;

use App\Models\Task;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TasksExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    use \App\Exports\Concerns\StylesSheet;

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
        return $query->get();
    }

    public function map($task): array
    {
        return [
            $task->title ?? '',
            $task->assignee?->name ?? '',
            $task->case?->case_number ?? '',
            $task->case?->title ?? '',
            $task->status ?? '',
            $task->priority ?? '',
            $task->due_date?->format('Y/m/d') ?? '',
            $task->completed_at?->format('Y/m/d') ?? '',
        ];
    }

    public function headings(): array
    {
        return ['المهمة', 'المحامي المسؤول', 'رقم القضية', 'القضية', 'الحالة', 'الأولوية', 'تاريخ الاستحقاق', 'تاريخ الإنجاز'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}
