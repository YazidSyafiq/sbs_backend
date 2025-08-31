<?php

namespace App\Filament\Resources\PurchaseProductResource\Pages;

use App\Filament\Resources\PurchaseProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseProducts extends ListRecords
{
    protected static string $resource = PurchaseProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Purchase Order')
                ->icon('heroicon-m-shopping-cart'),
        ];
    }
}
