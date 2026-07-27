<?php

namespace App\Http\Controllers;

use App\Models\FinanceFee;
use App\Models\FinanceInvoice;
use App\Models\FinanceTransaction;
use App\Models\Client;
use App\Models\LegalCase;
use Illuminate\Http\Request;
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
            'unpaid_invoices_amount' => FinanceInvoice::whereIn('status', ['unpaid', 'partial'])->sum(FinanceInvoice::raw('amount - paid_amount')),
        ];

        return view('finance.index', compact('tab', 'transactions', 'invoices', 'fees', 'clients', 'cases', 'stats'));
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
        ]);
        $data['user_id'] = auth()->id();
        FinanceTransaction::create($data);
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم إضافة المعاملة');
    }

    public function storeInvoice(Request $request)
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|unique:finance_invoices,invoice_number',
            'client_id' => 'nullable|exists:clients,id',
            'amount' => 'required|numeric|min:0',
            'status' => 'required|in:paid,unpaid,partial,cancelled',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);
        $data['paid_amount'] = $data['status'] === 'paid' ? $data['amount'] : 0;
        $data['user_id'] = auth()->id();
        FinanceInvoice::create($data);
        return redirect()->route('finance.index', ['tab' => 'invoices'])->with('success', 'تم إضافة الفاتورة');
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

    public function destroyTransaction(FinanceTransaction $transaction)
    {
        $transaction->delete();
        return redirect()->route('finance.index', ['tab' => 'transactions'])->with('success', 'تم حذف المعاملة');
    }

    public function destroyInvoice(FinanceInvoice $invoice)
    {
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
