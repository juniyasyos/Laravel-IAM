<?php

namespace App\Filament\Panel\Resources\AccessProfiles;

use App\Filament\Panel\Resources\AccessProfiles\Pages\CreateAccessProfile;
use App\Filament\Panel\Resources\AccessProfiles\Pages\EditAccessProfile;
use App\Filament\Panel\Resources\AccessProfiles\Pages\ListAccessProfiles;
use App\Filament\Panel\Resources\AccessProfiles\Schemas\AccessProfileForm;
use App\Filament\Panel\Resources\AccessProfiles\Tables\AccessProfilesTable;
use App\Domain\Iam\Models\AccessProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccessProfileResource extends Resource
{
    protected static ?string $model = AccessProfile::class;

    protected static string|UnitEnum|null $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Access Profiles';

    protected static ?string $modelLabel = 'Access Profile';

    protected static ?string $pluralModelLabel = 'Access Profiles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AccessProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccessProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RolesRelationManager::class,
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessProfiles::route('/'),
            'create' => CreateAccessProfile::route('/create'),
            'edit' => EditAccessProfile::route('/{record}/edit'),
        ];
    }
}
