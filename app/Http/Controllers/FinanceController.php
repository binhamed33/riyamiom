<?php

namespace App\Http\Controllers;

use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\Client;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\Attachments;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    protected function isAdmin(): bool
    {
        return in_array(auth()->user()->role, ['developer', 'admin', 'lawyer', 'staff']);
    }

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'transactions');
        $isFinAdmin = $this->isAdmin();
        $userId = auth()->id();

        $userCaseIds = LegalCase::where('lawyer_id', $userId)->pluck('id');

        $transactions = FinanceTransaction::with('user')
            ->when(!$isFinAdmin, fn($q) => $q->where('user_id', $userId))
            ->latest()->paginate(20);
        $invoices = FinanceInvoice::with('client')
            ->when(!$isFinAdmin, fn($q) => $q->where('user_id', $userId))
            ->latest()->paginate(20);
        $fees = FinanceFee::with(['case', 'user'])
            ->when(!$isFinAdmin, fn($q) => $q->whereIn('case_id', $userCaseIds))
            ->latest()->paginate(20);

        $clients = Client::orderBy('name')->get();
        $cases = LegalCase::with('client')->when(!$isFinAdmin, fn($q) => $q->where('lawyer_id', $userId))->orderBy('office_case_number')->get();

        $stats = [];
        $incomeByCategory = collect();
        $expenseByCategory = collect();
        $monthlyIncome = collect();
        $monthlyExpense = collect();

        if ($isFinAdmin) {
            $stats = [
                'total_income' => FinanceTransaction::where('type', 'income')->sum('amount'),
                'total_expense' => FinanceTransaction::where('type', 'expense')->sum('amount'),
                'balance' => FinanceTransaction::where('type', 'income')->sum('amount') - FinanceTransaction::where('type', 'expense')->sum('amount'),
                'pending_invoices' => FinanceInvoice::whereIn('status', ['unpaid', 'partial'])->count(),
                'unpaid_invoices_amount' => FinanceInvoice::whereIn('status', ['unpaid', 'partial'])->selectRaw('sum(amount - paid_amount) as total')->value('total') ?? 0,
            ];

            $incomeByCategory = FinanceTransaction::where('type', 'income')->selectRaw('category, sum(amount) as total')->groupBy('category')->pluck('total', 'category');
            $expenseByCategory = FinanceTransaction::where('type', 'expense')->selectRaw('category, sum(amount) as total')->groupBy('category')->pluck('total', 'category');
            // التجميع الشهري في PHP لا في SQL.
            //
            // كان DATE_FORMAT — وهي دالة MySQL وحدها. فصفحة المالية
            // كلّها تنهار على sqlite، أي أنها غير قابلة للاختبار
            // إطلاقاً: لا اختبار واحد يمرّ عليها، ولا ثغرة فيها تُكتشف.
            // وقد اختبأت فيها ثغرة XSS مخزَّنة حتى فُحصت يدوياً.
            //
            // معاملات مكتبٍ واحد لا تُثقل الذاكرة، والتجميع هنا يُزيل
            // اعتماداً على محرّك بعينه ويفتح الصفحة للاختبار.
            $byMonth = static fn (string $type) => FinanceTransaction::where('type', $type)
                ->orderBy('date')
                ->get(['date', 'amount'])
                ->groupBy(fn ($t) => \Illuminate\Support\Carbon::parse($t->date)->format('Y-m'))
                ->map(fn ($rows) => (float) $rows->sum('amount'));

            $monthlyIncome = $byMonth('income');
            $monthlyExpense = $byMonth('expense');
        }

        return view('finance.index', compact('tab', 'transactions', 'invoices', 'fees', 'clients', 'cases', 'stats', 'incomeByCategory', 'expenseByCategory', 'monthlyIncome', 'monthlyExpense', 'isFinAdmin'));
    }

    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:income,expense',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'payment_method' => 'nullable|string',
            'reference' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);
        $data['user_id'] = auth()->id();

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', Attachments::DISK);
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        FinanceTransaction::create($data);
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم إضافة المعاملة');
    }

    public function updateTransaction(Request $request, FinanceTransaction $transaction)
    {
        abort_unless($this->isAdmin() || $transaction->user_id === auth()->id(), 403);
        $data = $request->validate([
            'type' => 'required|in:income,expense',
            'category' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'payment_method' => 'nullable|string',
            'reference' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);

        if ($request->hasFile('attachment')) {
            if ($transaction->attachment_path) Attachments::delete($transaction->attachment_path);
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', Attachments::DISK);
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        $transaction->update($data);
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم تحديث المعاملة');
    }

    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|unique:finance_invoices,invoice_number',
            'client_id' => 'nullable|exists:clients,id',
            'case_id' => 'nullable|exists:cases,id',
            'client_visible' => 'nullable|boolean',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:paid,unpaid,partial,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);
        $data['paid_amount'] = $data['paid_amount'] ?? ($data['status'] === 'paid' ? $data['amount'] : 0);
        $data['client_visible'] = (bool) ($data['client_visible'] ?? false);
        $data['user_id'] = auth()->id();

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', Attachments::DISK);
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        FinanceInvoice::create($data);
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم إضافة الفاتورة');
    }

    public function updateInvoice(Request $request, FinanceInvoice $invoice)
    {
        abort_unless($this->isAdmin() || $invoice->user_id === auth()->id(), 403);
        $data = $request->validate([
            'invoice_number' => 'required|string|unique:finance_invoices,invoice_number,' . $invoice->id,
            'client_id' => 'nullable|exists:clients,id',
            'case_id' => 'nullable|exists:cases,id',
            'client_visible' => 'nullable|boolean',
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:paid,unpaid,partial,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);
        $data['paid_amount'] = $data['paid_amount'] ?? ($data['status'] === 'paid' ? $data['amount'] : 0);

        if ($request->hasFile('attachment')) {
            if ($invoice->attachment_path) Attachments::delete($invoice->attachment_path);
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', Attachments::DISK);
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        $invoice->update($data);
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم تحديث الفاتورة');
    }

    public function storeFee(Request $request)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'fee_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:paid,unpaid',
            'client_visible' => 'nullable|boolean',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'return_to_case' => 'nullable|boolean',
        ]);
        $case = LegalCase::findOrFail($data['case_id']);
        abort_unless($this->isAdmin() || $case->lawyer_id === auth()->id(), 403);

        $returnToCase = (bool) ($data['return_to_case'] ?? false);
        unset($data['return_to_case']);

        $data['client_visible'] = (bool) ($data['client_visible'] ?? false);
        $data['user_id'] = auth()->id();
        FinanceFee::create($data);

        // من أضاف الرسم من صفحة القضية يعود إليها لا إلى القسم المالي
        return $returnToCase
            ? redirect()->route('cases.show', $case)->with('success', 'تم إضافة الرسم إلى القضية')
            : redirect()->route('finance.index', ['tab' => 'fees'])->with('success', 'تم إضافة الرسم');
    }

    public function updateFee(Request $request, FinanceFee $fee)
    {
        abort_unless($this->isAdmin() || $fee->user_id === auth()->id(), 403);
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'fee_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:paid,unpaid',
            'client_visible' => 'nullable|boolean',
            'date' => 'required|date',
            'description' => 'nullable|string',
        ]);
        $data['client_visible'] = (bool) ($data['client_visible'] ?? false);
        $fee->update($data);
        return redirect()->route('finance.index', ['tab' => 'fees'])->with('success', 'تم تحديث الرسم');
    }

    public function showTransaction(FinanceTransaction $transaction)
    {
        abort_unless($this->isAdmin() || $transaction->user_id === auth()->id(), 403);
        return view('finance.show', compact('transaction'));
    }

    public function showInvoice(FinanceInvoice $invoice)
    {
        abort_unless($this->isAdmin() || $invoice->user_id === auth()->id(), 403);
        return view('finance.show', compact('invoice'));
    }

    public function showFee(FinanceFee $fee)
    {
        abort_unless($this->isAdmin() || $fee->user_id === auth()->id() || $fee->case->lawyer_id === auth()->id(), 403);
        return view('finance.show', compact('fee'));
    }

    public function printTransaction(FinanceTransaction $transaction)
    {
        abort_unless($this->isAdmin() || $transaction->user_id === auth()->id(), 403);
        return view('finance.print', ['item' => $transaction, 'type' => 'transaction']);
    }

    public function printInvoice(FinanceInvoice $invoice)
    {
        abort_unless($this->isAdmin() || $invoice->user_id === auth()->id(), 403);
        return view('finance.print', ['item' => $invoice, 'type' => 'invoice']);
    }

    public function printFee(FinanceFee $fee)
    {
        abort_unless($this->isAdmin() || $fee->user_id === auth()->id() || $fee->case->lawyer_id === auth()->id(), 403);
        return view('finance.print', ['item' => $fee, 'type' => 'fee']);
    }

    public function destroyTransaction(FinanceTransaction $transaction)
    {
        abort_unless($this->isAdmin() || $transaction->user_id === auth()->id(), 403);
        if ($transaction->attachment_path) Attachments::delete($transaction->attachment_path);
        $transaction->delete();
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم حذف المعاملة');
    }

    public function destroyInvoice(FinanceInvoice $invoice)
    {
        abort_unless($this->isAdmin() || $invoice->user_id === auth()->id(), 403);
        if ($invoice->attachment_path) Attachments::delete($invoice->attachment_path);
        $invoice->delete();
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم حذف الفاتورة');
    }

    public function destroyFee(FinanceFee $fee)
    {
        abort_unless($this->isAdmin() || $fee->user_id === auth()->id(), 403);
        $fee->delete();
        return redirect()->route('finance.index', ['tab' => 'fees'])->with('success', 'تم حذف الرسم');
    }

    public function payInvoice(FinanceInvoice $invoice)
    {
        abort_unless($this->isAdmin(), 403);
        $invoice->update(['status' => 'paid', 'paid_amount' => $invoice->amount]);
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم تسديد الفاتورة');
    }

    /**
     * مرفقُ قيدٍ ماليّ — إيصالٌ أو فاتورة — لا يُقدَّم إلا لمن يملك
     * الدخول إلى الماليّة. كان يُقدَّم من ‎/storage/…‎ بلا تحقّق، وكان
     * لا يُفتح أصلاً لأنّ الرابط الرمزيّ غير موجود.
     */
    public function transactionAttachment(Request $request, FinanceTransaction $transaction): StreamedResponse
    {
        return $this->serveAttachment($request, $transaction->attachment_path, $transaction->attachment_name);
    }

    public function invoiceAttachment(Request $request, FinanceInvoice $invoice): StreamedResponse
    {
        return $this->serveAttachment($request, $invoice->attachment_path, $invoice->attachment_name);
    }

    private function serveAttachment(Request $request, ?string $path, ?string $name): StreamedResponse
    {
        abort_if($path === null || $path === '', 404, 'لا مرفق لهذا القيد.');

        return Attachments::respond($request, $path, $name, null);
    }
}
