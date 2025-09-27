<?php

namespace App\Filament\Panel\Resources\Users;

use App\Filament\Panel\Resources\Users\Pages\CreateUser;
use App\Filament\Panel\Resources\Users\Pages\EditUser;
use App\Filament\Panel\Resources\Users\Pages\ListUsers;
use App\Filament\Panel\Resources\Users\Pages\ViewUser;
use App\Filament\Panel\Resources\Users\RelationManagers\ApplicationsRelationManager;
use App\Filament\Panel\Resources\Users\RelationManagers\RolesRelationManager;
use App\Filament\Panel\Resources\Users\Schemas\UserForm;
use App\Filament\Panel\Resources\Users\Schemas\UserInfolist;
use App\Filament\Panel\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | UnitEnum | null $navigationGroup = 'IAM';

    protected static ?int $navigationSort = 30;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?string $navigationLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RolesRelationManager::class,
            ApplicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
