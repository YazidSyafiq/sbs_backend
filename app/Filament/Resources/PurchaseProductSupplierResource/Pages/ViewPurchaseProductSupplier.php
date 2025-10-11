<?php

namespace App\Filament\Resources\PurchaseProductSupplierResource\Pages;

use App\Filament\Resources\PurchaseProductSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseProductSupplier extends ViewRecord
{
    protected static string $resource = PurchaseProductSupplierResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\EditAction::make()
    //             ->label('Edit PO')
    //             ->icon('heroicon-s-pencil-square'),
    //     ];
    // }
}
