<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseProductResource extends JsonResource
{
    private function formatPrice($price)
    {
        // Convert to float first, then format without decimals
        return number_format((float)$price, 0, '', '');
    }

    public function toArray($request)
    {
        if ($request->routeIs('*.getList') || !$this->relationLoaded('items')) {
            return [
                'id' => $this->id,
                'po_number' => $this->po_number,
                'user' => $this->user->name ?? null,
                'order_date' => $this->order_date ? $this->order_date->format('Y-m-d') : null,
                'type_po' => $this->type_po,
                'status' => $this->status,
                'status_paid' => $this->status_paid,
                'total_amount' =>$this->formatPrice($this->total_amount),
            ];
        }

        // Response lengkap untuk detail
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'po_name' => $this->name,
            'user' => $this->user->name ?? null,
            'order_date' => $this->order_date ? $this->order_date->format('Y-m-d') : null,
            'expected_delivery_date' => $this->expected_delivery_date ? $this->expected_delivery_date->format('Y-m-d') : null,
            'status' => $this->status,
            'type_po' => $this->type_po,
            'status_paid' => $this->status_paid,
            'total_amount' => $this->formatPrice($this->total_amount),
            'notes' => $this->notes,
            'bukti_tf' => $this->bukti_tf ? url('storage/'. $this->bukti_tf) : null,
            'items' => PurchaseProductItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
