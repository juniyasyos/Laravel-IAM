<?php

namespace App\Filament\Panel\Resources\Users\RelationManagers;

use App\Filament\Panel\Resources\Applications\ApplicationResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $relatedResource = ApplicationResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                // TODO: Hubungkan ke Spatie teams (application) pada iterasi berikutnya.
                TextColumn::make('name')
                    ->label('Application')
                    ->searchable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->label('Enabled'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->bulkActions([]);
    }
}
