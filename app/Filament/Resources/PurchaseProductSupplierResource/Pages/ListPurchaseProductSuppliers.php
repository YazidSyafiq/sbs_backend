<?php

namespace App\Filament\Resources\PurchaseProductSupplierResource\Pages;

use App\Filament\Resources\PurchaseProductSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseProductSuppliers extends ListRecords
{
    protected static string $resource = PurchaseProductSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Purchase Order')
                ->icon('heroicon-m-shopping-cart'),
        ];
    }
}
