<?php

namespace App\Filament\Panel\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TODO: Hubungkan ke Spatie teams (application) pada iterasi berikutnya.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                // TODO: Hubungkan ke Spatie teams (application) pada iterasi berikutnya.
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->recordActions([])
            ->bulkActions([]);
    }
}
