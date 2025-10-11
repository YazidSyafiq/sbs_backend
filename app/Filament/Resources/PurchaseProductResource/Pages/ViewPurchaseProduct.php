<?php

namespace App\Filament\Resources\PurchaseProductResource\Pages;

use App\Filament\Resources\PurchaseProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseProduct extends ViewRecord
{
    protected static string $resource = PurchaseProductResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\EditAction::make()
    //             ->label('Edit PO')
    //             ->icon('heroicon-s-pencil-square'),
    //     ];
    // }
}
