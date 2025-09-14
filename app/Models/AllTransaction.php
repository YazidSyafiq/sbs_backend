<?php

namespace App\Models;

use App\Models\Income;
use App\Models\Expense;
use App\Models\PurchaseProduct;
use App\Models\ServicePurchase;
use App\Models\PurchaseProductSupplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class AllTransaction extends Model
{
    // Gunakan table incomes sebagai base (sama seperti AccountingReport)
    protected $table = 'incomes';

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected $fillable = [
        'transaction_type',
        'transaction_id',
        'po_number',
        'transaction_name',
        'date',
        'branch',
        'user',
        'status',
        'payment_status',
        'item_type',
        'item_name',
        'item_code',
        'category',
        'quantity',
        'unit_price',
        'total_amount',
        'cost_price',
        'profit',
        'profit_margin',
        'supplier_technician',
        'description',
    ];

    /**
     * Override newCollection to create virtual records from all transactions
     */
    public function newCollection(array $models = [])
    {
        $filters = session('all_transaction_filters', []);
        $transactions = $this->getAllTransactions($filters);

        // Apply additional filters
        $transactions = $this->applyAdditionalFilters($transactions, $filters);

        return new Collection($transactions);
    }

    /**
     * Apply additional filters to transactions - MADE PUBLIC
     */
    public function applyAdditionalFilters($transactions, $filters = [])
    {
        $filteredTransactions = collect($transactions);

        // Filter by transaction type
        if (!empty($filters['transaction_types'])) {
            $filteredTransactions = $filteredTransactions->whereIn('transaction_type', $filters['transaction_types']);
        }

        // Filter by payment status
        if (!empty($filters['payment_statuses'])) {
            $filteredTransactions = $filteredTransactions->whereIn('payment_status', $filters['payment_statuses']);
        }

        // Filter by item type
        if (!empty($filters['item_types'])) {
            $filteredTransactions = $filteredTransactions->whereIn('item_type', $filters['item_types']);
        }

        // Filter by branch
        if (!empty($filters['branch'])) {
            $filteredTransactions = $filteredTransactions->where('branch', $filters['branch']);
        }

        // Filter by user
        if (!empty($filters['user'])) {
            $filteredTransactions = $filteredTransactions->where('user', 'like', '%' . $filters['user'] . '%');
        }

        return $filteredTransactions->values()->all();
    }

    /**
     * Get all transactions from various sources
     */
    public function getAllTransactions($filters = [])
    {
        // Default to last 12 months if no filters
        if (empty($filters['date_from']) && empty($filters['date_until'])) {
            $dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = Carbon::now()->endOfMonth()->toDateString();
        } else {
            $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : Carbon::now()->endOfMonth()->toDateString();
        }

        $transactions = collect();

        // 1. Income transactions
        $incomes = Income::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        foreach ($incomes as $income) {
            $virtualRecord = new static();
            $virtualRecord->id = 'income_' . $income->id;
            $virtualRecord->transaction_type = 'Income';
            $virtualRecord->transaction_id = $income->id;
            $virtualRecord->po_number = null;
            $virtualRecord->transaction_name = $income->name;
            $virtualRecord->date = $income->date;
            $virtualRecord->branch = null;
            $virtualRecord->user = null;
            $virtualRecord->status = 'Completed';
            $virtualRecord->payment_status = 'Received';
            $virtualRecord->item_type = 'Income';
            $virtualRecord->item_name = $income->name;
            $virtualRecord->item_code = null;
            $virtualRecord->category = 'Income';
            $virtualRecord->quantity = 1;
            $virtualRecord->unit_price = $income->income_amount;
            $virtualRecord->total_amount = $income->income_amount;
            $virtualRecord->cost_price = 0;
            $virtualRecord->profit = $income->income_amount;
            $virtualRecord->profit_margin = 100;
            $virtualRecord->supplier_technician = null;
            $virtualRecord->description = $income->description;
            $virtualRecord->exists = true;

            $transactions->push($virtualRecord);
        }

        // 2. Expense transactions
        $expenses = Expense::whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date', 'desc')
            ->get();

        foreach ($expenses as $expense) {
            $virtualRecord = new static();
            $virtualRecord->id = 'expense_' . $expense->id;
            $virtualRecord->transaction_type = 'Expense';
            $virtualRecord->transaction_id = $expense->id;
            $virtualRecord->po_number = null;
            $virtualRecord->transaction_name = $expense->name;
            $virtualRecord->date = $expense->date;
            $virtualRecord->branch = null;
            $virtualRecord->user = null;
            $virtualRecord->status = 'Completed';
            $virtualRecord->payment_status = 'Paid';
            $virtualRecord->item_type = 'Expense';
            $virtualRecord->item_name = $expense->name;
            $virtualRecord->item_code = null;
            $virtualRecord->category = 'Expense';
            $virtualRecord->quantity = 1;
            $virtualRecord->unit_price = -$expense->expense_amount;
            $virtualRecord->total_amount = -$expense->expense_amount;
            $virtualRecord->cost_price = $expense->expense_amount;
            $virtualRecord->profit = -$expense->expense_amount;
            $virtualRecord->profit_margin = -100;
            $virtualRecord->supplier_technician = null;
            $virtualRecord->description = $expense->description;
            $virtualRecord->exists = true;

            $transactions->push($virtualRecord);
        }

        // 3. PO Product transactions (dengan detail items)
        $poProducts = PurchaseProduct::with(['items.product.category', 'user.branch'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($poProducts as $po) {
            foreach ($po->items as $item) {
                $revenue = $po->status_paid === 'paid' ? $item->total_price : 0;
                $cost = $po->status_paid === 'paid' ? (($item->cost_price ?? 0) * $item->quantity) : 0;
                $profit = $revenue - $cost;
                $profitMargin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

                $virtualRecord = new static();
                $virtualRecord->id = 'po_product_' . $po->id . '_' . $item->id;
                $virtualRecord->transaction_type = 'PO Product';
                $virtualRecord->transaction_id = $po->id;
                $virtualRecord->po_number = $po->po_number;
                $virtualRecord->transaction_name = $po->name;
                $virtualRecord->date = $po->order_date;
                $virtualRecord->branch = $po->user->branch->name ?? 'No Branch';
                $virtualRecord->user = $po->user->name ?? 'Unknown User';
                $virtualRecord->status = $po->status;
                $virtualRecord->payment_status = ucfirst($po->status_paid ?? 'Pending');
                $virtualRecord->item_type = 'Product';
                $virtualRecord->item_name = $item->product->name ?? 'Unknown Product';
                $virtualRecord->item_code = $item->product->code ?? 'N/A';
                $virtualRecord->category = $item->product->category->name ?? 'No Category';
                $virtualRecord->quantity = $item->quantity;
                $virtualRecord->unit_price = $item->unit_price;
                $virtualRecord->total_amount = $revenue;
                $virtualRecord->cost_price = ($item->cost_price ?? 0) * $item->quantity;
                $virtualRecord->profit = $profit;
                $virtualRecord->profit_margin = $profitMargin;
                $virtualRecord->supplier_technician = null;
                $virtualRecord->description = $po->notes;
                $virtualRecord->exists = true;

                $transactions->push($virtualRecord);
            }
        }

        // 4. Service Purchase transactions (dengan detail items)
        $servicePos = ServicePurchase::with(['items.service.category', 'items.technician', 'user.branch'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($servicePos as $po) {
            foreach ($po->items as $item) {
                $revenue = $po->status_paid === 'paid' ? $item->selling_price : 0;
                $cost = $po->status_paid === 'paid' ? ($item->cost_price ?? 0) : 0;
                $profit = $revenue - $cost;
                $profitMargin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

                $virtualRecord = new static();
                $virtualRecord->id = 'po_service_' . $po->id . '_' . $item->id;
                $virtualRecord->transaction_type = 'PO Service';
                $virtualRecord->transaction_id = $po->id;
                $virtualRecord->po_number = $po->po_number;
                $virtualRecord->transaction_name = $po->name;
                $virtualRecord->date = $po->order_date;
                $virtualRecord->branch = $po->user->branch->name ?? 'No Branch';
                $virtualRecord->user = $po->user->name ?? 'Unknown User';
                $virtualRecord->status = $po->status;
                $virtualRecord->payment_status = ucfirst($po->status_paid ?? 'Pending');
                $virtualRecord->item_type = 'Service';
                $virtualRecord->item_name = $item->service->name ?? 'Unknown Service';
                $virtualRecord->item_code = $item->service->code ?? 'N/A';
                $virtualRecord->category = $item->service->category->name ?? 'No Category';
                $virtualRecord->quantity = 1;
                $virtualRecord->unit_price = $item->selling_price;
                $virtualRecord->total_amount = $revenue;
                $virtualRecord->cost_price = $item->cost_price ?? 0;
                $virtualRecord->profit = $profit;
                $virtualRecord->profit_margin = $profitMargin;
                $virtualRecord->supplier_technician = $item->technician->name ?? 'Not Assigned';
                $virtualRecord->description = $po->notes;
                $virtualRecord->exists = true;

                $transactions->push($virtualRecord);
            }
        }

        // 5. Supplier PO transactions
        $supplierPos = PurchaseProductSupplier::with(['product.category', 'supplier', 'user'])
            ->whereNotIn('status', ['Cancelled'])
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->orderBy('order_date', 'desc')
            ->get();

        foreach ($supplierPos as $po) {
            $cost = $po->status_paid === 'paid' ? $po->total_amount : 0;

            $virtualRecord = new static();
            $virtualRecord->id = 'po_supplier_' . $po->id;
            $virtualRecord->transaction_type = 'PO Supplier';
            $virtualRecord->transaction_id = $po->id;
            $virtualRecord->po_number = $po->po_number;
            $virtualRecord->transaction_name = $po->name;
            $virtualRecord->date = $po->order_date;
            $virtualRecord->branch = null;
            $virtualRecord->user = $po->user->name ?? 'Unknown User';
            $virtualRecord->status = $po->status;
            $virtualRecord->payment_status = ucfirst($po->status_paid ?? 'Pending');
            $virtualRecord->item_type = 'Supplier Purchase';
            $virtualRecord->item_name = $po->product->name ?? 'Unknown Product';
            $virtualRecord->item_code = $po->product->code ?? 'N/A';
            $virtualRecord->category = $po->product->category->name ?? 'No Category';
            $virtualRecord->quantity = $po->quantity;
            $virtualRecord->unit_price = $po->unit_price;
            $virtualRecord->total_amount = 0; // Ini adalah pengeluaran, bukan revenue
            $virtualRecord->cost_price = $cost;
            $virtualRecord->profit = -$cost; // Negative karena ini cost
            $virtualRecord->profit_margin = $cost > 0 ? -100 : 0;
            $virtualRecord->supplier_technician = $po->supplier->name ?? 'Unknown Supplier';
            $virtualRecord->description = $po->notes;
            $virtualRecord->exists = true;

            $transactions->push($virtualRecord);
        }

        return $transactions->sortByDesc('date')->values()->all();
    }

    /**
     * Apply filters (bisa dipanggil dari resource)
     */
    public static function applyFilters($filters = [])
    {
        session(['all_transaction_filters' => $filters]);
    }

    /**
     * Get period label
     */
    public static function getPeriodLabel($filters = [])
    {
        if (!empty($filters['date_from']) || !empty($filters['date_until'])) {
            $dateRange = '';
            if (!empty($filters['date_from'])) {
                $dateRange .= 'From ' . Carbon::parse($filters['date_from'])->format('d M Y');
            }
            if (!empty($filters['date_until'])) {
                if ($dateRange) $dateRange .= ' ';
                $dateRange .= 'To ' . Carbon::parse($filters['date_until'])->format('d M Y');
            }
            return $dateRange;
        }

        return 'Last 12 Months';
    }
}
