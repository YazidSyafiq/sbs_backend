<?php

namespace App\Filament\Resources\ProductBatchResource\Pages;

use App\Filament\Resources\ProductBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductBatch extends EditRecord
{
    protected static string $resource = ProductBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View Batch')
                ->color('primary')
                ->icon('heroicon-s-eye'),
        ];
    }
}
