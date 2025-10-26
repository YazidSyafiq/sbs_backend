<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseSupplierResource extends JsonResource
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
        if ($request->routeIs('*.getList')) {
            return [
                'id' => $this->id,
                'po_number' => $this->po_number,
                'user' => $this->user->name ?? null,
                'supplier' => $this->supplier->name ?? null,
                'code_supplier' => $this->supplier->code ?? null,
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
            'supplier' => $this->supplier->name ?? null,
            'code_supplier' => $this->supplier->code ?? null,
            'order_date' => $this->order_date ? $this->order_date->format('Y-m-d') : null,
            'received_date' => $this->received_date ? $this->received_date->format('Y-m-d') : null,
            'status' => $this->status,
            'type_po' => $this->type_po,
            'status_paid' => $this->status_paid,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'product_code' => $this->product->code ?? null,
            'product_unit' => $this->product->unit ?? null,
            'quantity' => $this->quantity ? $this->formatQuantity($this->quantity) : null,
            'unit_price' => $this->unit_price ? $this->formatPrice($this->unit_price) : null,
            'total_amount' => $this->formatPrice($this->total_amount),
            'notes' => $this->notes,
            'bukti_tf' => $this->bukti_tf ? url('storage/'. $this->bukti_tf) : null,
            'faktur_url' => url('purchase-supplier/' . $this->id . '/faktur-supplier'),
        ];
    }
}
