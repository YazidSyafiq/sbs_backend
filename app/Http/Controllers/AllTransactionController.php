<?php

namespace App\Http\Controllers;

use App\Models\AllTransaction;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AllTransactionController extends Controller
{
    private function getCompanyInfo()
    {
        return [
            'name' => env('COMPANY_NAME', 'Your Company Name'),
            'address' => env('COMPANY_ADDRESS', 'Jl. Example Street No. 123'),
            'city' => env('COMPANY_CITY', 'Jakarta, Indonesia'),
            'phone' => env('COMPANY_PHONE', '+62 21 1234 5678'),
            'email' => env('COMPANY_EMAIL', 'info@yourcompany.com'),
            'website' => env('COMPANY_WEBSITE', 'www.yourcompany.com'),
        ];
    }

    public function report(Request $request)
    {
        // Get filter parameters
        $fromDate = $request->from_date ? Carbon::parse($request->from_date) : Carbon::now()->subMonths(11)->startOfMonth();
        $untilDate = $request->until_date ? Carbon::parse($request->until_date) : Carbon::now();
        $transactionTypes = $request->transaction_types ?? [];
        $paymentStatuses = $request->payment_statuses ?? [];
        $itemTypes = $request->item_types ?? [];
        $branch = $request->branch;
        $user = $request->user;
        $minAmount = $request->min_amount;
        $maxAmount = $request->max_amount;

        // Set session filters untuk getAllTransactions
        $filters = [
            'date_from' => $fromDate->toDateString(),
            'date_until' => $untilDate->toDateString(),
            'transaction_types' => $transactionTypes,
            'payment_statuses' => $paymentStatuses,
            'item_types' => $itemTypes,
            'branch' => $branch,
            'user' => $user,
        ];

        // Get transactions using AllTransaction model
        $allTransactionModel = new AllTransaction();
        $transactions = $allTransactionModel->getAllTransactions($filters);

        // Apply additional filters
        $transactions = $allTransactionModel->applyAdditionalFilters($transactions, $filters);

        // Convert to collection for easier manipulation
        $transactions = collect($transactions);

        // Apply amount filters
        if ($minAmount) {
            $transactions = $transactions->filter(function($transaction) use ($minAmount) {
                return abs($transaction->total_amount) >= $minAmount;
            });
        }

        if ($maxAmount) {
            $transactions = $transactions->filter(function($transaction) use ($maxAmount) {
                return abs($transaction->total_amount) <= $maxAmount;
            });
        }

        // Sort by date desc
        $transactions = $transactions->sortByDesc('date')->values();

        // Calculate totals
        $totalTransactions = $transactions->count();
        $totalRevenue = $transactions->where('total_amount', '>', 0)->sum('total_amount');
        $totalExpense = abs($transactions->where('total_amount', '<', 0)->sum('total_amount'));
        $netProfit = $totalRevenue - $totalExpense;
        $totalProfit = $transactions->sum('profit');

        // Transaction type summary
        $transactionTypeSummary = $transactions->groupBy('transaction_type')->map(function($items, $type) {
            return [
                'count' => $items->count(),
                'total_amount' => $items->sum('total_amount'),
                'avg_amount' => $items->count() > 0 ? $items->sum('total_amount') / $items->count() : 0,
            ];
        });

        // Payment status summary
        $paymentStatusSummary = $transactions->groupBy('payment_status')->map(function($items, $status) {
            return [
                'count' => $items->count(),
                'total_amount' => $items->sum('total_amount'),
            ];
        });

        // Branch summary (only for transactions with branch info)
        $branchSummary = $transactions->filter(function($transaction) {
            return $transaction->branch &&
                   $transaction->branch !== '-' &&
                   $transaction->branch !== 'No Branch' &&
                   $transaction->branch !== null;
        })->groupBy('branch')->map(function($items, $branchName) {
            return [
                'count' => $items->count(),
                'total_amount' => $items->sum('total_amount'),
                'profit' => $items->sum('profit'),
            ];
        });

        // Monthly summary
        $monthlySummary = $transactions->groupBy(function($transaction) {
            return Carbon::parse($transaction->date)->format('Y-m');
        })->map(function($items, $month) {
            return [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'count' => $items->count(),
                'revenue' => $items->where('total_amount', '>', 0)->sum('total_amount'),
                'expense' => abs($items->where('total_amount', '<', 0)->sum('total_amount')),
                'profit' => $items->sum('profit'),
            ];
        });

        // Get filter labels for display
        $filterLabels = $this->getFilterLabels($request);
        $companyInfo = $this->getCompanyInfo();

        return view('all-transaction.report', compact(
            'transactions',
            'fromDate',
            'untilDate',
            'totalTransactions',
            'totalRevenue',
            'totalExpense',
            'netProfit',
            'totalProfit',
            'transactionTypeSummary',
            'paymentStatusSummary',
            'branchSummary',
            'monthlySummary',
            'filterLabels',
            'companyInfo'
        ));
    }

    private function getFilterLabels(Request $request)
    {
        $labels = [];

        if ($request->transaction_types && count($request->transaction_types) > 0) {
            $labels['transaction_types'] = implode(', ', $request->transaction_types);
        }

        if ($request->payment_statuses && count($request->payment_statuses) > 0) {
            $labels['payment_statuses'] = implode(', ', $request->payment_statuses);
        }

        if ($request->item_types && count($request->item_types) > 0) {
            $labels['item_types'] = implode(', ', $request->item_types);
        }

        if ($request->branch) {
            $labels['branch'] = $request->branch;
        }

        if ($request->user) {
            $labels['user'] = $request->user;
        }

        if ($request->min_amount) {
            $labels['min_amount'] = 'Min Amount: Rp ' . number_format($request->min_amount, 0, ',', '.');
        }

        if ($request->max_amount) {
            $labels['max_amount'] = 'Max Amount: Rp ' . number_format($request->max_amount, 0, ',', '.');
        }

        return $labels;
    }
}
