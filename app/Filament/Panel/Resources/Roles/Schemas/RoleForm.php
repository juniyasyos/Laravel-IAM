<?php

namespace App\Filament\Panel\Resources\Roles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->scopedUnique(
                        model: \Spatie\Permission\Models\Role::class,
                        column: 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('guard_name', $get('guard_name') ?? config('auth.defaults.guard', 'web')),
                    ),
                TextInput::make('guard_name')
                    ->label('Guard Name')
                    ->default(fn (): string => config('auth.defaults.guard', 'web'))
                    ->disabled()
                    ->dehydrated()
                    ->maxLength(255),
            ]);
    }
}
