<?php

namespace App\Exports;

use App\Models\POReportProduct;
use App\Models\Branch;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Carbon\Carbon;
use Auth;

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
        $user = Auth::user();

        // Basic sheets for all users
        $sheets = [
            'Summary' => new POProductSummarySheet($this->filters),
            'Detailed Data' => new POProductDetailSheet($this->filters),
        ];

        // Add profit analysis sheet ONLY for Admin, Supervisor, Manager, Super Admin
        if ($user && !$user->hasRole('User')) {
            $sheets['Profit Analysis'] = new POProductProfitSheet($this->filters);
        }

        return $sheets;
    }
}
