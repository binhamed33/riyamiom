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
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'transactions');

        $transactions = FinanceTransaction::with('user')->latest()->paginate(20);
        $invoices = FinanceInvoice::with('client')->latest()->paginate(20);
        $fees = FinanceFee::with(['case', 'user'])->latest()->paginate(20);

        $clients = Client::orderBy('name')->get();
        $cases = LegalCase::orderBy('case_number')->get();

        $stats = [
            'total_income' => FinanceTransaction::where('type', 'income')->sum('amount'),
            'total_expense' => FinanceTransaction::where('type', 'expense')->sum('amount'),
            'balance' => FinanceTransaction::where('type', 'income')->sum('amount') - FinanceTransaction::where('type', 'expense')->sum('amount'),
            'pending_invoices' => FinanceInvoice::whereIn('status', ['unpaid', 'partial'])->count(),
            'unpaid_invoices_amount' => FinanceInvoice::whereIn('status', ['unpaid', 'partial'])->selectRaw('sum(amount - paid_amount) as total')->value('total') ?? 0,
        ];

        // Chart data
        $incomeByCategory = FinanceTransaction::where('type', 'income')->selectRaw('category, sum(amount) as total')->groupBy('category')->pluck('total', 'category');
        $expenseByCategory = FinanceTransaction::where('type', 'expense')->selectRaw('category, sum(amount) as total')->groupBy('category')->pluck('total', 'category');
        $monthlyIncome = FinanceTransaction::where('type', 'income')->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, sum(amount) as total")->groupBy('month')->orderBy('month')->pluck('total', 'month');
        $monthlyExpense = FinanceTransaction::where('type', 'expense')->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, sum(amount) as total")->groupBy('month')->orderBy('month')->pluck('total', 'month');

        return view('finance.index', compact('tab', 'transactions', 'invoices', 'fees', 'clients', 'cases', 'stats', 'incomeByCategory', 'expenseByCategory', 'monthlyIncome', 'monthlyExpense'));
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
            $data['attachment_path'] = $file->store('finance-attachments', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        FinanceTransaction::create($data);
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم إضافة المعاملة');
    }

    public function updateTransaction(Request $request, FinanceTransaction $transaction)
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

        if ($request->hasFile('attachment')) {
            if ($transaction->attachment_path) Storage::disk('public')->delete($transaction->attachment_path);
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', 'public');
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
            'amount' => 'required|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:paid,unpaid,partial,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
        ]);
        $data['paid_amount'] = $data['paid_amount'] ?? ($data['status'] === 'paid' ? $data['amount'] : 0);
        $data['user_id'] = auth()->id();

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        FinanceInvoice::create($data);
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم إضافة الفاتورة');
    }

    public function updateInvoice(Request $request, FinanceInvoice $invoice)
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|unique:finance_invoices,invoice_number,' . $invoice->id,
            'client_id' => 'nullable|exists:clients,id',
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
            if ($invoice->attachment_path) Storage::disk('public')->delete($invoice->attachment_path);
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('finance-attachments', 'public');
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
            'date' => 'required|date',
            'description' => 'nullable|string',
        ]);
        $data['user_id'] = auth()->id();
        FinanceFee::create($data);
        return redirect()->route('finance.index', ['tab' => 'fees'])->with('success', 'تم إضافة الرسم');
    }

    public function updateFee(Request $request, FinanceFee $fee)
    {
        $data = $request->validate([
            'case_id' => 'required|exists:cases,id',
            'fee_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:paid,unpaid',
            'date' => 'required|date',
            'description' => 'nullable|string',
        ]);
        $fee->update($data);
        return redirect()->route('finance.index', ['tab' => 'fees'])->with('success', 'تم تحديث الرسم');
    }

    public function showTransaction(FinanceTransaction $transaction)
    {
        return view('finance.show', compact('transaction'));
    }

    public function showInvoice(FinanceInvoice $invoice)
    {
        return view('finance.show', compact('invoice'));
    }

    public function showFee(FinanceFee $fee)
    {
        return view('finance.show', compact('fee'));
    }

    public function printTransaction(FinanceTransaction $transaction)
    {
        return view('finance.print', ['item' => $transaction, 'type' => 'transaction']);
    }

    public function printInvoice(FinanceInvoice $invoice)
    {
        return view('finance.print', ['item' => $invoice, 'type' => 'invoice']);
    }

    public function printFee(FinanceFee $fee)
    {
        return view('finance.print', ['item' => $fee, 'type' => 'fee']);
    }

    public function destroyTransaction(FinanceTransaction $transaction)
    {
        if ($transaction->attachment_path) Storage::disk('public')->delete($transaction->attachment_path);
        $transaction->delete();
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم حذف المعاملة');
    }

    public function destroyInvoice(FinanceInvoice $invoice)
    {
        if ($invoice->attachment_path) Storage::disk('public')->delete($invoice->attachment_path);
        $invoice->delete();
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم حذف الفاتورة');
    }

    public function destroyFee(FinanceFee $fee)
    {
        $fee->delete();
        return redirect()->route('finance.index', ['tab' => 'fees'])->with('success', 'تم حذف الرسم');
    }

    public function payInvoice(FinanceInvoice $invoice)
    {
        $invoice->update(['status' => 'paid', 'paid_amount' => $invoice->amount]);
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم تسديد الفاتورة');
    }
}
