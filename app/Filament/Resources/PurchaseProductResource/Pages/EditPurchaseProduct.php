<?php

namespace App\Filament\Resources\PurchaseProductResource\Pages;

use App\Filament\Resources\PurchaseProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseProduct extends EditRecord
{
    protected static string $resource = PurchaseProductResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\ViewAction::make()
    //             ->label('View PO')
    //             ->color('primary')
    //             ->icon('heroicon-s-eye'),
    //     ];
    // }
}
