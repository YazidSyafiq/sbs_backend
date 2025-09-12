<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Product;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View Product')
                ->color('primary')
                ->icon('heroicon-s-eye'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $oldCode = $this->record->code;

        if (isset($data['code_id']) && $data['code_id'] != $this->record->code_id) {
            $data['code'] = Product::generateCode($data['code_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Check apakah code berubah dan update batch numbers jika perlu
        if ($this->record->wasChanged('code')) {
            $this->record->updateProductBatchNumbers();
        }
    }
}
