<?php

namespace App\Filament\Resources\ServicePurchaseResource\Pages;

use App\Filament\Resources\ServicePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServicePurchases extends ListRecords
{
    protected static string $resource = ServicePurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Purchase Order')
                ->icon('heroicon-m-shopping-cart'),
        ];
    }
}
