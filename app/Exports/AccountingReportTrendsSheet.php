<?php

namespace App\Exports;

use App\Models\AccountingReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AccountingReportTrendsSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function array(): array
    {
        $data = [];

        // Header
        $data[] = ['FINANCIAL TRENDS ANALYSIS REPORT'];
        $data[] = ['Generated on: ' . now()->format('d M Y H:i:s')];
        $data[] = ['Period: ' . AccountingReport::getPeriodLabel($this->filters)];
        $data[] = [''];

        // Monthly Trends
        $monthlyTrends = AccountingReport::getMonthlyProfitTrends($this->filters);
        if ($monthlyTrends->count() > 0) {
            $data[] = ['FINANCIAL TRENDS OVER TIME'];
            $data[] = ['Period', 'Total Revenue', 'Total Cost', 'Gross Profit', 'Profit Margin %'];

            foreach ($monthlyTrends as $trend) {
                $data[] = [
                    $trend->period_name,
                    'Rp ' . number_format($trend->total_revenue, 0, ',', '.'),
                    'Rp ' . number_format($trend->total_cost, 0, ',', '.'),
                    'Rp ' . number_format($trend->gross_profit, 0, ',', '.'),
                    $trend->profit_margin . '%'
                ];
            }
            $data[] = [''];

            // Trend Analysis
            $data[] = ['TREND ANALYSIS'];
            $data[] = [''];

            // Calculate trends
            $revenueData = $monthlyTrends->pluck('total_revenue');
            $costData = $monthlyTrends->pluck('total_cost');
            $profitData = $monthlyTrends->pluck('gross_profit');

            $avgRevenue = $revenueData->avg();
            $avgCost = $costData->avg();
            $avgProfit = $profitData->avg();

            $maxRevenue = $revenueData->max();
            $minRevenue = $revenueData->min();
            $maxCost = $costData->max();
            $minCost = $costData->min();
            $maxProfit = $profitData->max();
            $minProfit = $profitData->min();

            // Best and worst performing periods
            $bestProfitPeriod = $monthlyTrends->where('gross_profit', $maxProfit)->first();
            $worstProfitPeriod = $monthlyTrends->where('gross_profit', $minProfit)->first();
            $highestRevenuePeriod = $monthlyTrends->where('total_revenue', $maxRevenue)->first();
            $lowestRevenuePeriod = $monthlyTrends->where('total_revenue', $minRevenue)->first();

            $data[] = ['PERFORMANCE METRICS'];
            $data[] = ['Average Revenue per Period', 'Rp ' . number_format($avgRevenue, 0, ',', '.')];
            $data[] = ['Average Cost per Period', 'Rp ' . number_format($avgCost, 0, ',', '.')];
            $data[] = ['Average Profit per Period', 'Rp ' . number_format($avgProfit, 0, ',', '.')];
            $data[] = [''];

            $data[] = ['PEAK PERFORMANCE'];
            $data[] = ['Highest Revenue Period', $highestRevenuePeriod ? $highestRevenuePeriod->period_name . ' (Rp ' . number_format($maxRevenue, 0, ',', '.') . ')' : 'N/A'];
            $data[] = ['Best Profit Period', $bestProfitPeriod ? $bestProfitPeriod->period_name . ' (Rp ' . number_format($maxProfit, 0, ',', '.') . ')' : 'N/A'];
            $data[] = ['Highest Cost Period', $monthlyTrends->where('total_cost', $maxCost)->first()->period_name . ' (Rp ' . number_format($maxCost, 0, ',', '.') . ')'];
            $data[] = [''];

            $data[] = ['LOW PERFORMANCE'];
            $data[] = ['Lowest Revenue Period', $lowestRevenuePeriod ? $lowestRevenuePeriod->period_name . ' (Rp ' . number_format($minRevenue, 0, ',', '.') . ')' : 'N/A'];
            $data[] = ['Worst Profit Period', $worstProfitPeriod ? $worstProfitPeriod->period_name . ' (Rp ' . number_format($minProfit, 0, ',', '.') . ')' : 'N/A'];
            $data[] = ['Lowest Cost Period', $monthlyTrends->where('total_cost', $minCost)->first()->period_name . ' (Rp ' . number_format($minCost, 0, ',', '.') . ')'];
            $data[] = [''];

            // Volatility Analysis
            $revenueVariance = $revenueData->count() > 1 ? sqrt($revenueData->map(fn($x) => pow($x - $avgRevenue, 2))->sum() / ($revenueData->count() - 1)) : 0;
            $profitVariance = $profitData->count() > 1 ? sqrt($profitData->map(fn($x) => pow($x - $avgProfit, 2))->sum() / ($profitData->count() - 1)) : 0;

            $data[] = ['VOLATILITY ANALYSIS'];
            $data[] = ['Revenue Volatility (Std Dev)', 'Rp ' . number_format($revenueVariance, 0, ',', '.')];
            $data[] = ['Profit Volatility (Std Dev)', 'Rp ' . number_format($profitVariance, 0, ',', '.')];
            $data[] = ['Revenue Range', 'Rp ' . number_format($maxRevenue - $minRevenue, 0, ',', '.')];
            $data[] = ['Profit Range', 'Rp ' . number_format($maxProfit - $minProfit, 0, ',', '.')];
            $data[] = [''];

            // Growth Analysis (if more than 1 period)
            if ($monthlyTrends->count() > 1) {
                $firstPeriod = $monthlyTrends->first();
                $lastPeriod = $monthlyTrends->last();

                $revenueGrowth = $firstPeriod->total_revenue > 0 ?
                    round((($lastPeriod->total_revenue - $firstPeriod->total_revenue) / $firstPeriod->total_revenue) * 100, 2) : 0;
                $profitGrowth = $firstPeriod->gross_profit > 0 ?
                    round((($lastPeriod->gross_profit - $firstPeriod->gross_profit) / abs($firstPeriod->gross_profit)) * 100, 2) : 0;

                $data[] = ['GROWTH ANALYSIS'];
                $data[] = ['Period-over-Period Revenue Growth', $revenueGrowth . '%'];
                $data[] = ['Period-over-Period Profit Growth', $profitGrowth . '%'];
                $data[] = ['Revenue Trend', $revenueGrowth > 0 ? 'Growing' : ($revenueGrowth < 0 ? 'Declining' : 'Stable')];
                $data[] = ['Profit Trend', $profitGrowth > 0 ? 'Growing' : ($profitGrowth < 0 ? 'Declining' : 'Stable')];
            }

        } else {
            $data[] = ['No trend data available for the selected period.'];
        }

        return $data;
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7C3AED']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Monthly Trends';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 20,
            'C' => 20,
            'D' => 20,
            'E' => 15,
        ];
    }
}
