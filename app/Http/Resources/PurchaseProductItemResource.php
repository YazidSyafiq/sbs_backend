<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseProductItemResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'product_code' => $this->product->code ?? null,
            'product_unit' => $this->product->unit ?? null,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price ? $this->formatPrice($this->unit_price) : null,
            'total_price' => $this->total_price ? $this->formatPrice($this->total_price) : null,
            'cost_price' => $this->cost_price ? $this->formatPrice($this->cost_price) : null,
            'profit_amount' => $this->profit_amount ? $this->formatPrice($this->profit_amount) : null,
            'profit_margin' => $this->profit_margin,
        ];
    }
}
