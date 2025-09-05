<?php

namespace App\Exports;

use App\Models\POReportSupplierProduct;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Carbon\Carbon;

class POReportSupplierProductExport implements WithMultipleSheets
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
            'Summary' => new POSupplierProductSummarySheet($this->filters),
            'Detailed Data' => new POSupplierProductDetailSheet($this->filters),
        ];
    }
}
