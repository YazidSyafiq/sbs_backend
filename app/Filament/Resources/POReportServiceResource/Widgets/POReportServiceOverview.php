<?php

namespace App\Filament\Resources\POReportServiceResource\Widgets;

use App\Models\POReportService;
use App\Models\Branch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Auth;

class POReportServiceOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_service_filters', []);
        $user = Auth::user();
        $isUserRole = $user && $user->hasRole('User');

        // Use POReportService model method
        $stats = POReportService::getFilteredOverviewStats($filters);

        // Get contextual stats for the 6th card
        $contextStats = $this->getContextualStats($filters, $stats, $isUserRole);

        return [
            Stat::make('Total Service Orders', number_format($stats->total_count))
                ->description('All active service POs')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('info'),

            Stat::make('Total Service Value', 'Rp ' . number_format($stats->total_po_amount, 0, ',', '.'))
                ->description($isUserRole ? 'Total service purchase amount' : 'All service PO amounts combined')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make($isUserRole ? 'Amount Paid' : 'Amount Received', 'Rp ' . number_format($stats->paid_amount, 0, ',', '.'))
                ->description($stats->payment_rate . '% of total service value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make($isUserRole ? 'Outstanding Payment' : 'Outstanding Debt', 'Rp ' . number_format($stats->outstanding_debt, 0, ',', '.'))
                ->description((100 - $stats->payment_rate) . '% of total service value')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats->outstanding_debt > 0 ? 'danger' : 'success'),

            Stat::make('Payment Rate', $stats->payment_rate . '%')
                ->description('Payment completion rate')
                ->descriptionIcon($stats->payment_rate >= 80 ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                ->color($stats->payment_rate >= 80 ? 'success' : ($stats->payment_rate >= 50 ? 'warning' : 'danger')),

            // Context-aware 6th stat
            Stat::make($contextStats['title'], $contextStats['value'])
                ->description($contextStats['description'])
                ->descriptionIcon($contextStats['icon'])
                ->color($contextStats['color']),
        ];
    }

    /**
     * Get contextual statistics based on active filters
     */
    private function getContextualStats($filters, $currentStats, $isUserRole): array
    {
        // Check what filters are active
        $hasDateFilter = !empty($filters['date_from']) || !empty($filters['date_until']);
        $hasTypeFilter = !empty($filters['type_po']);
        $hasStatusFilter = !empty($filters['status']) || !empty($filters['status_paid']);
        $hasTechnicianFilter = !empty($filters['technician_id']);
        $hasOutstandingFilter = !empty($filters['outstanding_only']);

        if ($hasTechnicianFilter) {
            return $this->getTechnicianFilterContext($filters, $currentStats, $isUserRole);
        } elseif ($hasDateFilter) {
            return $this->getDateFilterContext($filters, $currentStats, $isUserRole);
        } elseif ($hasTypeFilter) {
            return $this->getTypeFilterContext($filters, $currentStats, $isUserRole);
        } elseif ($hasStatusFilter) {
            return $this->getStatusFilterContext($filters, $currentStats, $isUserRole);
        } elseif ($hasOutstandingFilter) {
            return $this->getOutstandingFilterContext($currentStats, $isUserRole);
        } else {
            return $this->getDefaultContext($isUserRole);
        }
    }

    private function getTechnicianFilterContext($filters, $currentStats, $isUserRole): array
    {
        $technicianName = \App\Models\Technician::find($filters['technician_id'])->name ?? 'Unknown Technician';

        $description = 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)';

        return [
            'title' => 'Technician: ' . substr($technicianName, 0, 12) . (strlen($technicianName) > 12 ? '...' : ''),
            'value' => number_format($currentStats->total_count) . ' services',
            'description' => $description,
            'icon' => 'heroicon-m-user-circle',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    private function getDateFilterContext($filters, $currentStats, $isUserRole): array
    {
        $dateFrom = !empty($filters['date_from']) ? $filters['date_from'] : null;
        $dateTo = !empty($filters['date_until']) ? $filters['date_until'] : null;

        if ($dateFrom && $dateTo) {
            $daysDiff = \Carbon\Carbon::parse($dateFrom)->diffInDays(\Carbon\Carbon::parse($dateTo));

            if ($daysDiff <= 7) {
                $periodType = 'Week';
                $icon = 'heroicon-m-calendar';
            } elseif ($daysDiff <= 31) {
                $periodType = 'Month';
                $icon = 'heroicon-m-calendar';
            } elseif ($daysDiff <= 92) {
                $periodType = 'Quarter';
                $icon = 'heroicon-m-chart-bar';
            } elseif ($daysDiff <= 365) {
                $periodType = 'Year';
                $icon = 'heroicon-m-calendar-days';
            } else {
                $periodType = 'Period';
                $icon = 'heroicon-m-calendar-days';
            }

            $title = "Selected {$periodType}";
        } elseif ($dateFrom) {
            $title = 'From ' . \Carbon\Carbon::parse($dateFrom)->format('M Y');
            $icon = 'heroicon-m-arrow-right';
        } else {
            $title = 'Until ' . \Carbon\Carbon::parse($dateTo)->format('M Y');
            $icon = 'heroicon-m-arrow-left';
        }

        $description = 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)';

        return [
            'title' => $title,
            'value' => number_format($currentStats->total_count) . ' services',
            'description' => $description,
            'icon' => $icon ?? 'heroicon-m-calendar-days',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    private function getTypeFilterContext($filters, $currentStats, $isUserRole): array
    {
        $types = array_map('ucfirst', $filters['type_po']);
        $typeText = implode(' & ', $types);

        $description = 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)';

        return [
            'title' => $typeText . ' Services',
            'value' => number_format($currentStats->total_count) . ' orders',
            'description' => $description,
            'icon' => count($types) === 1 && $types[0] === 'Credit' ? 'heroicon-m-credit-card' : 'heroicon-m-wrench-screwdriver',
            'color' => count($types) === 1 && $types[0] === 'Credit' ? 'warning' : 'success'
        ];
    }

    private function getStatusFilterContext($filters, $currentStats, $isUserRole): array
    {
        $hasStatus = !empty($filters['status']);
        $hasPaymentStatus = !empty($filters['status_paid']);

        if ($hasPaymentStatus && count($filters['status_paid']) === 1) {
            $paymentStatus = ucfirst($filters['status_paid'][0]);
            $title = $paymentStatus . ' Services';
            $icon = $paymentStatus === 'Paid' ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle';
            $color = $paymentStatus === 'Paid' ? 'success' : 'danger';
        } elseif ($hasStatus && count($filters['status']) === 1) {
            $status = $filters['status'][0];
            $title = $status . ' Services';
            $icon = match($status) {
                'Requested' => 'heroicon-m-paper-airplane',
                'Approved' => 'heroicon-m-check-circle',
                'In Progress' => 'heroicon-m-cog',
                'Done' => 'heroicon-m-check-badge',
                default => 'heroicon-m-clipboard-document-check'
            };
            $color = match($status) {
                'Requested' => 'amber',
                'Approved' => 'blue',
                'In Progress' => 'purple',
                'Done' => 'success',
                default => 'info'
            };
        } else {
            $title = 'Filtered Status';
            $icon = 'heroicon-m-funnel';
            $color = 'purple';
        }

        $description = 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)';

        return [
            'title' => $title,
            'value' => number_format($currentStats->total_count) . ' orders',
            'description' => $description,
            'icon' => $icon,
            'color' => $color
        ];
    }

    private function getOutstandingFilterContext($currentStats, $isUserRole): array
    {
        $description = $isUserRole ?
            number_format($currentStats->total_count) . ' service orders need payment' :
            number_format($currentStats->total_count) . ' unpaid service orders need attention';

        return [
            'title' => $isUserRole ? 'Unpaid Services' : 'Outstanding Services',
            'value' => 'Rp ' . number_format($currentStats->outstanding_debt, 0, ',', '.'),
            'description' => $description,
            'icon' => 'heroicon-m-exclamation-triangle',
            'color' => 'danger'
        ];
    }

    private function getDefaultContext($isUserRole): array
    {
        // Show current month when no filters are applied
        $thisMonthFilters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->endOfMonth()->toDateString(),
        ];
        $thisMonthStats = POReportService::getFilteredOverviewStats($thisMonthFilters);

        $description = $isUserRole ?
            'Paid: Rp ' . number_format($thisMonthStats->paid_amount, 0, ',', '.') . ' (' . $thisMonthStats->payment_rate . '%)' :
            'Received: Rp ' . number_format($thisMonthStats->paid_amount, 0, ',', '.') . ' (' . $thisMonthStats->payment_rate . '%)';

        return [
            'title' => 'This Month',
            'value' => 'Rp ' . number_format($thisMonthStats->total_po_amount, 0, ',', '.'),
            'description' => $description,
            'icon' => 'heroicon-m-calendar',
            'color' => $thisMonthStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    public function getHeading(): ?string
    {
        return 'Service Financial Overview';
    }
}
