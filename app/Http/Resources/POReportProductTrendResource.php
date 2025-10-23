<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class POReportProductTrendResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'period' => $this->period_name,
            'total_amount' => (float)$this->total_po_amount,
            'paid_amount' => (float)$this->paid_amount,
            'outstanding' => (float)$this->outstanding_debt,
            'total_orders' => $this->total_pos,
            'payment_rate' => $this->payment_rate,
        ];
    }
}
