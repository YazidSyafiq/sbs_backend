<?php

namespace App\Filament\Resources\PurchaseProductResource\Pages;

use App\Filament\Resources\PurchaseProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\PurchaseProduct;

class CreatePurchaseProduct extends CreateRecord
{
    protected static string $resource = PurchaseProductResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan user_id ter-set
        if (!isset($data['user_id']) || !$data['user_id']) {
            $data['user_id'] = auth()->id();
        }

        // Pastikan order_date ter-set
        if (!isset($data['order_date']) || !$data['order_date']) {
            $data['order_date'] = now()->format('Y-m-d');
        }

        // Generate PO number berdasarkan user dan order date
        $data['po_number'] = PurchaseProduct::generatePoNumber(
            $data['user_id'],
            $data['order_date']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->calculateTotal();
    }
}
