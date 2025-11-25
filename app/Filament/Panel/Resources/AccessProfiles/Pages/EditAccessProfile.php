<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Pages;

use App\Filament\Panel\Resources\AccessProfiles\AccessProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccessProfile extends EditRecord
{
    protected static string $resource = AccessProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
