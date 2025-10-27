<?php

namespace App\Filament\Resources\PurchaseProductSupplierResource\Pages;

use App\Filament\Resources\PurchaseProductSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseProductSupplier extends EditRecord
{
    protected static string $resource = PurchaseProductSupplierResource::class;

    protected function afterSave(): void
    {
        // Hitung ulang total setiap kali data disimpan
        $this->record->calculateTotal();
    }
}
