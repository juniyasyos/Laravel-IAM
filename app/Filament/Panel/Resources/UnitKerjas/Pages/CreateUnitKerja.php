<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Pages;

use App\Filament\Panel\Resources\UnitKerjas\UnitKerjaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnitKerja extends CreateRecord
{
    protected static string $resource = UnitKerjaResource::class;
    protected static bool $canCreateAnother = false;

    //customize redirect after create
    public function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record->slug]);
    }
}
