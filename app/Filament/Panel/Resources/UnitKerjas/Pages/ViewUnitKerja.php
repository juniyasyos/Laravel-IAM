<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Pages;

use App\Filament\Panel\Resources\UnitKerjas\UnitKerjaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnitKerja extends ViewRecord
{
    protected static string $resource = UnitKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
