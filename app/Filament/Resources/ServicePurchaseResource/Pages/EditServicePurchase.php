<?php

namespace App\Filament\Resources\ServicePurchaseResource\Pages;

use App\Filament\Resources\ServicePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServicePurchase extends EditRecord
{
    protected static string $resource = ServicePurchaseResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\ViewAction::make(),
    //         Actions\DeleteAction::make(),
    //     ];
    // }
}
