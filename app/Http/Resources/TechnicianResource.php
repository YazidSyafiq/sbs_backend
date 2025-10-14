<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'price' => $this->price ? $this->formatPrice($this->price) : null,
            'piutang' => $this->piutang ? $this->formatPrice($this->piutang) : null,
            'total_po' => $this->total_po ? $this->formatPrice($this->total_po) : null,
        ];
    }
}
