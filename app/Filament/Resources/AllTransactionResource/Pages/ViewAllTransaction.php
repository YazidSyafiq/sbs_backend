<?php

namespace App\Filament\Resources\AllTransactionResource\Pages;

use App\Filament\Resources\AllTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAllTransaction extends ViewRecord
{
    protected static string $resource = AllTransactionResource::class;

    // Override mount to redirect back to index since view is disabled
    public function mount(int | string $record): void
    {
        $this->redirect($this->getResource()::getUrl('index'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
