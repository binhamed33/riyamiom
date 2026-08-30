<?php

namespace App\Exports;

use App\Models\LegalCase;
use App\Models\Session;
use App\Models\Task;
use App\Models\Client;
use App\Models\FinanceTransaction;
use App\Models\FinanceInvoice;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
            new SummarySheet($user),
            new CasesSheet($user),
            new SessionsSheet($user),
            new TasksSheet($user),
            new ClientsSheet($user),
        ];
    }
}

// ─── Summary Sheet ────────────────────────────────────────────────

class SummarySheet implements FromArray, WithHeadings, ShouldAutoSize, WithEvents
{
    private $user;
    public function __construct($user) { $this->user = $user; }

    public function array(): array
    {
        $totalCases = LegalCase::count();
        $activeCases = LegalCase::where('status', 'active')->count();
        $overdueCases = LegalCase::where('status', 'overdue')->count();
        $closedCases = LegalCase::whereIn('status', ['closed', 'won', 'lost'])->count();
        $totalClients = Client::count();
        $totalTasks = Task::count();
        $pendingTasks = Task::where('status', 'pending')->count();
        $totalSessions = Session::count();
        $income = FinanceTransaction::where('type', 'income')->sum('amount');
        $expense = FinanceTransaction::where('type', 'expense')->sum('amount');
        $unpaidInvoices = FinanceInvoice::whereIn('status', ['unpaid', 'partial'])->count();

        return [
            ['', ''],
            ['ملخص إحصائي', ''],
            ['', ''],
            ['البيان', 'القيمة'],
            ['إجمالي القضايا', $totalCases],
            ['  القضايا النشطة', $activeCases],
            ['  القضايا المتأخرة', $overdueCases],
            ['  القضايا المغلقة', $closedCases],
            ['إجمالي العملاء', $totalClients],
            ['إجمالي المهام', $totalTasks],
            ['  المهام المعلقة', $pendingTasks],
            ['إجمالي الجلسات', $totalSessions],
            ['إجمالي الدخل', number_format($income, 2) . ' ر.ع'],
            ['إجمالي المصروفات', number_format($expense, 2) . ' ر.ع'],
            ['الرصيد', number_format($income - $expense, 2) . ' ر.ع'],
            ['الفواتير غير المسددة', $unpaidInvoices],
        ];
    }

    public function headings(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);
                $sheet->mergeCells('A1:B1');
                $sheet->mergeCells('A2:B2');

                $titleCell = $sheet->getStyle('A2');
                $titleCell->getFont()->setBold(true)->setSize(16);
                $titleCell->getFont()->getColor()->setARGB('D4AF37');

                // حبر داكن على أرضية فاتحة — كان أبيض على أبيض في الصفوف الفردية
                $sheet->getStyle('A4:B20')->getFont()->setSize(11)->getColor()->setARGB('1F2937');

                $headerStyle = $sheet->getStyle('A4:B4');
                $headerStyle->getFont()->setBold(true)->setSize(12);
                $headerStyle->getFont()->getColor()->setARGB('0A1628');
                $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D4AF37');
                $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(4)->setRowHeight(28);

                for ($row = 5; $row <= 20; $row++) {
                    $range = "A{$row}:B{$row}";
                    if ($row % 2 === 0) {
                        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FAF7F0');
                    }
                    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('E5E1D8');
                }
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setAutoSize(true);
            },
        ];
    }
}

// ─── Cases Sheet ──────────────────────────────────────────────────

class CasesSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    use \App\Exports\Concerns\StylesSheet;

    private $user;
    public function __construct($user) { $this->user = $user; }

    public function collection()
    {
        $query = LegalCase::with(['client', 'lawyer']);
        if ($this->user->isLawyer()) $query->where('lawyer_id', $this->user->id);
        return $query->get();
    }

    public function map($case): array
    {
        return [
            $case->case_number ?? '', $case->title ?? '', $case->client?->name ?? '',
            $case->lawyer?->name ?? '', $case->type ?? '', $case->court ?? '',
            $case->opponent ?? '', $case->status ?? '', $case->priority ?? '',
            $case->opened_at?->format('Y/m/d') ?? '', $case->next_date?->format('Y/m/d') ?? '',
            $case->notes ?? '',
        ];
    }

    public function headings(): array { return ['رقم القضية', 'العنوان', 'العميل', 'المحامي', 'النوع', 'المحكمة', 'الخصم', 'الحالة', 'الأولوية', 'تاريخ الفتح', 'الموعد القادم', 'ملاحظات']; }
    public function title(): string { return 'القضايا'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}

// ─── Sessions Sheet ──────────────────────────────────────────────

class SessionsSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    use \App\Exports\Concerns\StylesSheet;

    private $user;
    public function __construct($user) { $this->user = $user; }

    public function collection()
    {
        $query = Session::with('case');
        if ($this->user->isLawyer()) $query->whereHas('case', fn($q) => $q->where('lawyer_id', $this->user->id));
        return $query->get();
    }

    public function map($session): array
    {
        return [
            $session->case?->case_number ?? '', $session->case?->title ?? '',
            $session->date?->format('Y/m/d H:i') ?? '', $session->location ?? '',
            $session->status ?? '', $session->notes ?? '',
        ];
    }

    public function headings(): array { return ['رقم القضية', 'القضية', 'التاريخ', 'الموقع', 'الحالة', 'ملاحظات']; }
    public function title(): string { return 'الجلسات'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}

// ─── Tasks Sheet ─────────────────────────────────────────────────

class TasksSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    use \App\Exports\Concerns\StylesSheet;

    private $user;
    public function __construct($user) { $this->user = $user; }

    public function collection()
    {
        $query = Task::with(['assignee', 'case']);
        if ($this->user->isLawyer()) $query->where('assigned_to', $this->user->id);
        return $query->get();
    }

    public function map($task): array
    {
        return [
            $task->title ?? '', $task->assignee?->name ?? '', $task->case?->case_number ?? '',
            $task->case?->title ?? '', $task->status ?? '', $task->priority ?? '',
            $task->due_date?->format('Y/m/d') ?? '', $task->completed_at?->format('Y/m/d') ?? '',
        ];
    }

    public function headings(): array { return ['المهمة', 'المحامي المسؤول', 'رقم القضية', 'القضية', 'الحالة', 'الأولوية', 'تاريخ الاستحقاق', 'تاريخ الإنجاز']; }
    public function title(): string { return 'المهام'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}

// ─── Clients Sheet ───────────────────────────────────────────────

class ClientsSheet implements \Maatwebsite\Excel\Concerns\FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping
{
    use \App\Exports\Concerns\StylesSheet;

    private $user;
    public function __construct($user) { $this->user = $user; }

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
            $client->name ?? '', $client->phone ?? '', $client->email ?? '',
            $client->address ?? '', $client->cases->count(),
            $client->cases->where('status', 'active')->count(), $client->notes ?? '',
        ];
    }

    public function headings(): array { return ['الاسم', 'الهاتف', 'البريد الإلكتروني', 'العنوان', 'عدد القضايا', 'القضايا النشطة', 'ملاحظات']; }
    public function title(): string { return 'العملاء'; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}
