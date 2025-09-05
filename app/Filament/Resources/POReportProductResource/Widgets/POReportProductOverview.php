<?php

namespace App\Filament\Resources\POReportProductResource\Widgets;

use App\Models\POReportProduct;
use App\Models\Branch;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class POReportProductOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $filters = session('po_accounting_filters', []);

        // Use POReportProduct model method
        $stats = POReportProduct::getFilteredOverviewStats($filters);

        // Get contextual stats for the 6th card
        $contextStats = $this->getContextualStats($filters, $stats);

        return [
            Stat::make('Total Purchase Orders', number_format($stats->total_count))
                ->description('All active POs')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),

            Stat::make('Total PO Value', 'Rp ' . number_format($stats->total_po_amount, 0, ',', '.'))
                ->description('All PO amounts combined')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Amount Received', 'Rp ' . number_format($stats->paid_amount, 0, ',', '.'))
                ->description($stats->payment_rate . '% of total PO value')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Outstanding Debt', 'Rp ' . number_format($stats->outstanding_debt, 0, ',', '.'))
                ->description((100 - $stats->payment_rate) . '% of total PO value')
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
    private function getContextualStats($filters, $currentStats): array
    {
        // Check what filters are active
        $hasDateFilter = !empty($filters['date_from']) || !empty($filters['date_until']);
        $hasTypeFilter = !empty($filters['type_po']);
        $hasStatusFilter = !empty($filters['status']) || !empty($filters['status_paid']);
        $hasOutstandingFilter = !empty($filters['outstanding_only']);

        if ($hasDateFilter) {
            return $this->getDateFilterContext($filters, $currentStats);
        } elseif ($hasTypeFilter) {
            return $this->getTypeFilterContext($filters, $currentStats);
        } elseif ($hasStatusFilter) {
            return $this->getStatusFilterContext($filters, $currentStats);
        } elseif ($hasOutstandingFilter) {
            return $this->getOutstandingFilterContext($currentStats);
        } else {
            return $this->getDefaultContext();
        }
    }

    /**
     * Context for date filter
     */
    private function getDateFilterContext($filters, $currentStats): array
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

        return [
            'title' => $title,
            'value' => number_format($currentStats->total_count) . ' orders',
            'description' => 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)',
            'icon' => $icon ?? 'heroicon-m-calendar-days',
            'color' => $currentStats->payment_rate >= 80 ? 'success' : 'info'
        ];
    }

    /**
     * Context for type filter
     */
    private function getTypeFilterContext($filters, $currentStats): array
    {
        $types = array_map('ucfirst', $filters['type_po']);
        $typeText = implode(' & ', $types);

        return [
            'title' => $typeText . ' POs',
            'value' => number_format($currentStats->total_count) . ' orders',
            'description' => 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)',
            'icon' => count($types) === 1 && $types[0] === 'Credit' ? 'heroicon-m-credit-card' : 'heroicon-m-shopping-cart',
            'color' => count($types) === 1 && $types[0] === 'Credit' ? 'warning' : 'success'
        ];
    }

    /**
     * Context for status filter
     */
    private function getStatusFilterContext($filters, $currentStats): array
    {
        $hasStatus = !empty($filters['status']);
        $hasPaymentStatus = !empty($filters['status_paid']);

        if ($hasPaymentStatus && count($filters['status_paid']) === 1) {
            $paymentStatus = ucfirst($filters['status_paid'][0]);
            $title = $paymentStatus . ' Orders';
            $icon = $paymentStatus === 'Paid' ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle';
            $color = $paymentStatus === 'Paid' ? 'success' : 'danger';
        } elseif ($hasStatus && count($filters['status']) === 1) {
            $status = $filters['status'][0];
            $title = $status . ' Orders';
            $icon = match($status) {
                'Requested' => 'heroicon-m-paper-airplane',
                'Processing' => 'heroicon-m-cog',
                'Shipped' => 'heroicon-m-truck',
                'Received' => 'heroicon-m-check-circle',
                'Done' => 'heroicon-m-check-badge',
                default => 'heroicon-m-clipboard-document-check'
            };
            $color = match($status) {
                'Requested' => 'amber',
                'Processing' => 'blue',
                'Shipped' => 'purple',
                'Received' => 'emerald',
                'Done' => 'success',
                default => 'info'
            };
        } else {
            $title = 'Filtered Status';
            $icon = 'heroicon-m-funnel';
            $color = 'purple';
        }

        return [
            'title' => $title,
            'value' => number_format($currentStats->total_count) . ' orders',
            'description' => 'Total: Rp ' . number_format($currentStats->total_po_amount, 0, ',', '.') . ' (' . $currentStats->payment_rate . '% paid)',
            'icon' => $icon,
            'color' => $color
        ];
    }

    /**
     * Context for outstanding filter
     */
    private function getOutstandingFilterContext($currentStats): array
    {
        return [
            'title' => 'Outstanding Orders',
            'value' => 'Rp ' . number_format($currentStats->outstanding_debt, 0, ',', '.'),
            'description' => number_format($currentStats->total_count) . ' unpaid orders need attention',
            'icon' => 'heroicon-m-exclamation-triangle',
            'color' => 'danger'
        ];
    }

    /**
     * Default context when no filters are applied
     */
    private function getDefaultContext(): array
    {
        // Show current month when no filters are applied
        $thisMonthFilters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_until' => now()->endOfMonth()->toDateString(),
        ];
        $thisMonthStats = POReportProduct::getFilteredOverviewStats($thisMonthFilters);

        return [
            'title' => 'This Month',
            'value' => 'Rp ' . number_format($thisMonthStats->total_po_amount, 0, ',', '.'),
            'description' => 'Received: Rp ' . number_format($thisMonthStats->paid_amount, 0, ',', '.') . ' (' . $thisMonthStats->payment_rate . '%)',
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
        return 'Financial Overview';
    }
}
