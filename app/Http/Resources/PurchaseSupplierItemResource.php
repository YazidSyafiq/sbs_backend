<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseSupplierItemResource extends JsonResource
{
    private function formatPrice($price)
    {
        // Convert to float first, then format without decimals
        return number_format((float)$price, 0, '', '');
    }

    private function formatQuantity($quantity)
    {
        if (is_null($quantity)) {
            return null;
        }

        // Convert to float
        $qty = (float)$quantity;

        // Jika bulat, return sebagai integer (tanpa desimal)
        if ($qty == floor($qty)) {
            return (int)$qty;
        }

        // Jika ada desimal, return dengan desimal (max 2 digit)
        return round($qty, 2);
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'product_code' => $this->product->code ?? null,
            'product_unit' => $this->product->unit ?? null,
            'quantity' => $this->formatQuantity($this->quantity),
            'unit_price' => $this->unit_price ? $this->formatPrice($this->unit_price) : null,
            'total_price' => $this->total_price ? $this->formatPrice($this->total_price) : null,
        ];
    }
}
