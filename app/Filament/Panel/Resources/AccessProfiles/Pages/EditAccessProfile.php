<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Pages;

use App\Filament\Panel\Resources\AccessProfiles\AccessProfileResource;
use App\Filament\Panel\Resources\AccessProfiles\RelationManagers\UsersRelationManager;
use Filament\Actions\DeleteAction;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Filament\Resources\Pages\EditRecord;

class EditAccessProfile extends EditRecord
{
    protected static string $resource = AccessProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // RelationManagerAction::make()
            //     ->label('Manage Roles')
            //     ->record($this->getRecord())
            //     ->slideOver()
            //     ->relationManager(RolesRelationManager::make()),
            RelationManagerAction::make()
                ->label('Manage Users')
                ->slideOver()
                ->record($this->getRecord())
                ->relationManager(UsersRelationManager::make()),
            DeleteAction::make(),
        ];
    }

    /**
     * Temp holder for role ids from the form.
     */
    protected array $tempRoleIds = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->tempRoleIds = $data['role_ids'] ?? [];

        unset($data['role_ids']);

        return $data;
    }
}
