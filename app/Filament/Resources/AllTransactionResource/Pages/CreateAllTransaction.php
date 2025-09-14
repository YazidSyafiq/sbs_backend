<?php

namespace App\Filament\Resources\AllTransactionResource\Pages;

use App\Filament\Resources\AllTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAllTransaction extends CreateRecord
{
    protected static string $resource = AllTransactionResource::class;

    // Override mount to redirect back to index since create is disabled
    public function mount(): void
    {
        $this->redirect($this->getResource()::getUrl('index'));
    }
}
