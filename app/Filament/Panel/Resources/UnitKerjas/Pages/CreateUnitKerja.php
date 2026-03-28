<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Pages;

use App\Filament\Panel\Resources\UnitKerjas\UnitKerjaResource;
use Filament\Resources\Pages\CreateRecord;
use Juniyasyos\ManageUnitKerja\Filament\Resources\UnitKerjaResource\Pages\CreateUnitKerja as PagesCreateUnitKerja;

class CreateUnitKerja extends PagesCreateUnitKerja
{
    protected static string $resource = UnitKerjaResource::class;
}
