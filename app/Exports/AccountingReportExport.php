<?php

namespace App\Exports;

use App\Models\AccountingReport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AccountingReportExport implements WithMultipleSheets
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [
            'Financial Summary' => new AccountingReportSummarySheet($this->filters),
            'Cash Flow Analysis' => new AccountingReportCashFlowSheet($this->filters),
            'Monthly Trends' => new AccountingReportTrendsSheet($this->filters),
            'All Transactions' => new ComprehensiveTransactionDetailSheet($this->filters),
            'Product Items Detail' => new ProductItemsDetailSheet($this->filters),
            'Service Items Detail' => new ServiceItemsDetailSheet($this->filters),
            'Supplier Items Detail' => new SupplierItemsDetailSheet($this->filters),
            'Income & Expense Detail' => new IncomeExpenseDetailSheet($this->filters),
        ];
    }
}
