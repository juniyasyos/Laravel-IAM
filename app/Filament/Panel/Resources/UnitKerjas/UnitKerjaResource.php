<?php

namespace App\Filament\Panel\Resources\UnitKerjas;

use App\Filament\Panel\Resources\UnitKerjas\Pages\CreateUnitKerja;
use App\Filament\Panel\Resources\UnitKerjas\Pages\EditUnitKerja;
use App\Filament\Panel\Resources\UnitKerjas\Pages\ListUnitKerjas;
use App\Filament\Panel\Resources\UnitKerjas\Pages\ViewUnitKerja;
use App\Filament\Panel\Resources\UnitKerjas\Schemas\UnitKerjaForm;
use App\Filament\Panel\Resources\UnitKerjas\Schemas\UnitKerjaInfolist;
use App\Filament\Panel\Resources\UnitKerjas\Tables\UnitKerjasTable;
use App\Models\UnitKerja;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Juniyasyos\ManageUnitKerja\Filament\Resources\UnitKerjaResource as ResourcesUnitKerjaResource;

class UnitKerjaResource extends ResourcesUnitKerjaResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListUnitKerjas::route('/'),
            'create' => CreateUnitKerja::route('/create'),
            'edit' => EditUnitKerja::route('/{record:slug}/edit'),
        ];
    }
}
