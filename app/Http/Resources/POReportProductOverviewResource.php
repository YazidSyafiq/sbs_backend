<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class POReportProductOverviewResource extends JsonResource
{
    private function formatPrice($price)
    {
        return number_format((float)$price, 0, '', '');
    }

    public function toArray($request)
    {
        return [
            // Card utama
            'total_orders' => $this->total_count,
            'total_amount' => $this->formatPrice($this->total_po_amount),
            'paid_amount' => $this->formatPrice($this->paid_amount),
            'outstanding' => $this->formatPrice($this->outstanding_debt),
            'payment_rate' => $this->payment_rate,

            // Breakdown cash vs credit
            'cash_summary' => [
                'count' => $this->cash_count,
                'total' => $this->formatPrice($this->cash_total_amount),
                'paid' => $this->formatPrice($this->cash_paid_amount),
                'outstanding' => $this->formatPrice($this->cash_outstanding),
                'payment_rate' => $this->cash_payment_rate,
            ],
            'credit_summary' => [
                'count' => $this->credit_count,
                'total' => $this->formatPrice($this->credit_total_amount),
                'paid' => $this->formatPrice($this->credit_paid_amount),
                'outstanding' => $this->formatPrice($this->credit_outstanding),
                'payment_rate' => $this->credit_payment_rate,
            ],
        ];
    }
}
