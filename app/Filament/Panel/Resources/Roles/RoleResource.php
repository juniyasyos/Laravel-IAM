<?php

namespace App\Filament\Panel\Resources\Roles;

use App\Filament\Panel\Resources\Roles\Pages\CreateRole;
use App\Filament\Panel\Resources\Roles\Pages\EditRole;
use App\Filament\Panel\Resources\Roles\Pages\ListRoles;
use App\Filament\Panel\Resources\Roles\Pages\ViewRole;
use App\Filament\Panel\Resources\Roles\Schemas\RoleForm;
use App\Filament\Panel\Resources\Roles\Schemas\RoleInfolist;
use App\Filament\Panel\Resources\Roles\Tables\RolesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = \Spatie\Permission\Models\Role::class;

    protected static string | UnitEnum | null $navigationGroup = 'IAM';

    protected static ?int $navigationSort = 20;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Roles';

    protected static ?string $navigationLabel = 'Roles';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
