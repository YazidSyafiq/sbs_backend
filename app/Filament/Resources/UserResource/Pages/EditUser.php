<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Cek apakah role yang dipilih bukan User
        if (isset($data['roles'])) {
            $userRoleId = Role::where('name', 'User')->value('id');

            $hasUserRole = is_array($data['roles'])
                ? in_array($userRoleId, $data['roles'])
                : $data['roles'] == $userRoleId;

            // Jika tidak ada User role, set branch_id ke null
            if (!$hasUserRole) {
                $data['branch_id'] = null;
            }
        }

        if (array_key_exists('password', $data) && filled($data['password'])) {
            $this->record->password = Hash::make($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->id === Auth::id()) {
            session()->put('password_hash_' . $this->getGuardName(), $this->record->password);
        }
    }

    protected function getGuardName(): string
    {
        return Auth::getDefaultDriver();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View User')
                ->color('primary')
                ->icon('heroicon-s-eye'),
        ];
    }
}
