<?php

namespace App\Filament\Resources\ProductBatchResource\Pages;

use App\Filament\Resources\ProductBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProductBatch extends ViewRecord
{
    protected static string $resource = ProductBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Product Batch')
                ->icon('heroicon-s-pencil-square'),
        ];
    }
}
