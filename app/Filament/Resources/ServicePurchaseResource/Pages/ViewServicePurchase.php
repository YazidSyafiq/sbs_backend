<?php

namespace App\Filament\Resources\ServicePurchaseResource\Pages;

use App\Filament\Resources\ServicePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServicePurchase extends ViewRecord
{
    protected static string $resource = ServicePurchaseResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Actions\EditAction::make(),
    //     ];
    // }
}
