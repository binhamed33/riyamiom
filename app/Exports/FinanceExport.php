<?php

namespace App\Exports;

use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * ماليّةُ المكتب في ملفٍّ واحد: المعاملات، والفواتير، ورسوم القضايا.
 *
 * كانت صفحةُ التقارير بلا بطاقةٍ ماليّة، والمالُ لا يخرج إلا مجاميعَ
 * في ورقة ملخّص «تصدير الكل». والمسارُ مقصورٌ على developer وadmin
 * في routes/web.php — حارسُ FinanceController::isAdmin نفسُه.
 */
class FinanceExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [new FinanceTransactionsSheet(), new FinanceInvoicesSheet(), new FinanceFeesSheet()];
    }
}

abstract class FinanceSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithMapping, WithTitle
{
    use \App\Exports\Concerns\StylesSheet;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->styleSheet($event->sheet->getDelegate()),
        ];
    }
}

class FinanceTransactionsSheet extends FinanceSheet
{
    public function collection()
    {
        return FinanceTransaction::with('user')->orderByDesc('date')->get();
    }

    public function map($tx): array
    {
        return [
            $tx->type === 'income' ? 'دخل' : 'مصروف', $tx->category ?? '', (float) $tx->amount,
            $tx->date?->format('Y-m-d') ?? '', $tx->payment_method ?? '', $tx->reference ?? '',
            $tx->user?->name ?? '', $tx->description ?? '',
        ];
    }

    public function headings(): array { return ['النوع', 'التصنيف', 'المبلغ', 'التاريخ', 'طريقة الدفع', 'المرجع', 'بواسطة', 'الوصف']; }
    public function title(): string { return 'المعاملات'; }
}

class FinanceInvoicesSheet extends FinanceSheet
{
    public function collection()
    {
        return FinanceInvoice::with(['client', 'case'])->orderByDesc('issue_date')->get();
    }

    public function map($inv): array
    {
        return [
            $inv->invoice_number ?? '', $inv->client?->name ?? '', $inv->case?->case_number ?? '',
            (float) $inv->amount, (float) $inv->paid_amount, $inv->remaining_amount,
            __('app.invoice_status_' . $inv->status), $inv->issue_date?->format('Y-m-d') ?? '',
            $inv->due_date?->format('Y-m-d') ?? '', $inv->description ?? '',
        ];
    }

    public function headings(): array { return ['رقم الفاتورة', 'العميل', 'القضية', 'المبلغ', 'المدفوع', 'المتبقي', 'الحالة', 'تاريخ الإصدار', 'تاريخ الاستحقاق', 'الوصف']; }
    public function title(): string { return 'الفواتير'; }
}

class FinanceFeesSheet extends FinanceSheet
{
    public function collection()
    {
        return FinanceFee::with('case')->orderByDesc('date')->get();
    }

    public function map($fee): array
    {
        return [
            $fee->case?->case_number ?? '', $fee->fee_type ?? '', (float) $fee->amount,
            $fee->status === 'paid' ? 'مدفوع' : 'غير مدفوع', $fee->date?->format('Y-m-d') ?? '',
            $fee->description ?? '',
        ];
    }

    public function headings(): array { return ['القضية', 'نوع الرسم', 'المبلغ', 'الحالة', 'التاريخ', 'الوصف']; }
    public function title(): string { return 'رسوم القضايا'; }
}
