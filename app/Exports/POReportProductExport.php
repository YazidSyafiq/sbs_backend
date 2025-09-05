<?php

namespace App\Exports;

use App\Models\POReportProduct;
use App\Models\Branch;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Carbon\Carbon;

class POReportProductExport implements WithMultipleSheets
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
            'Summary' => new POProductSummarySheet($this->filters),
            'Detailed Data' => new POProductDetailSheet($this->filters),
        ];
    }
}
