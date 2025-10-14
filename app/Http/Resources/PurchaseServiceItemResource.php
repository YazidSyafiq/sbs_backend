<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseServiceItemResource extends JsonResource
{
    private function formatPrice($price)
    {
        // Convert to float first, then format without decimals
        return number_format((float)$price, 0, '', '');
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_name' => $this->service->name ?? null,
            'service_code' => $this->service->code ?? null,
            'technician_id' => $this->technician_id,
            'technician_name' => $this->technician->name ?? null,
            'technician_code' => $this->technician->code ?? null,
            'cost_price' => $this->cost_price ? $this->formatPrice($this->cost_price) : null,
            'selling_price' => $this->selling_price ? $this->formatPrice($this->selling_price) : null,
        ];
    }
}
