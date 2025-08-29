<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Service;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View Service')
                ->color('primary')
                ->icon('heroicon-s-eye'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['code_id']) && $data['code_id'] != $this->record->code_id) {
            $data['code'] = Service::generateCode($data['code_id']);
        }

        return $data;
    }
}
