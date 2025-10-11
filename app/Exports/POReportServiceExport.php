<?php

namespace App\Exports;

use App\Models\POReportService;
use App\Models\Branch;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Carbon\Carbon;
use Auth;

class POReportServiceExport implements WithMultipleSheets
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
            'Summary' => new POServiceSummarySheet($this->filters),
            'Detailed Data' => new POServiceDetailSheet($this->filters),
        ];

        // Add profit analysis sheet ONLY for Admin, Supervisor, Manager, Super Admin
        if ($user && !$user->hasRole('User')) {
            $sheets['Profit Analysis'] = new POServiceProfitSheet($this->filters);
            $sheets['Technician Analysis'] = new POServiceTechnicianSheet($this->filters);
        }

        return $sheets;
    }
}
