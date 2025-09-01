<?php

namespace App\Filament\Resources\PurchaseProductSupplierResource\Pages;

use App\Filament\Resources\PurchaseProductSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\PurchaseProductSupplier;

class CreatePurchaseProductSupplier extends CreateRecord
{
    protected static string $resource = PurchaseProductSupplierResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan order_date ter-set
        if (!isset($data['order_date']) || !$data['order_date']) {
            $data['order_date'] = now()->format('Y-m-d');
        }

        // Generate PO number berdasarkan user dan order date
        $data['po_number'] = PurchaseProductSupplier::generatePoNumber(
            $data['supplier_id'],
            $data['order_date']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->calculateTotal();
    }
}
