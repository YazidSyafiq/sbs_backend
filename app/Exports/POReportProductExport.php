<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Auth;

class POReportProductExport implements WithMultipleSheets
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        $user = Auth::user();
        $sheets = [];

        // Summary sheet untuk semua user
        $sheets[] = new POProductSummarySheet($this->filters);

        // Detail sheet untuk semua user
        $sheets[] = new POProductDetailSheet($this->filters);

        // Profit analysis sheet hanya untuk non-User roles
        if ($user && !$user->hasRole('User')) {
            $sheets[] = new POProductProfitSheet($this->filters);
        }

        return $sheets;
    }
}
