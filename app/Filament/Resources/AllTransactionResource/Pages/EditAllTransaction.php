<?php

namespace App\Filament\Resources\AllTransactionResource\Pages;

use App\Filament\Resources\AllTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAllTransaction extends EditRecord
{
    protected static string $resource = AllTransactionResource::class;

    // Override mount to redirect back to index since edit is disabled
    public function mount(int | string $record): void
    {
        $this->redirect($this->getResource()::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
