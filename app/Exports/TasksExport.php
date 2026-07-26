<?php

namespace App\Exports;

use App\Models\Task;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TasksExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
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
        return $query->get()->map(function ($task) {
            return [
                $task->title ?? '',
                $task->assignee?->name ?? '',
                $task->case?->title ?? '',
                $task->status ?? '',
                $task->priority ?? '',
                $task->due_date?->format('Y/m/d') ?? '',
                $task->completed_at?->format('Y/m/d') ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['المهمة', 'المحامي المسؤول', 'القضية', 'الحالة', 'الأولوية', 'تاريخ الاستحقاق', 'تاريخ الإنجاز'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 12]]];
    }
}
