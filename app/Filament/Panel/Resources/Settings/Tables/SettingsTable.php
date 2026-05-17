<?php

namespace App\Filament\Panel\Resources\Settings\Tables;

use App\Models\Setting;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Database Settings')
            ->description('Kelola konfigurasi yang sudah dipindahkan dari file config ke database.')
            ->defaultSort('group')
            ->striped()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->searchPlaceholder('Cari key, group, atau deskripsi setting...')
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('semibold')
                    ->description(fn (Setting $record) => $record->description),

                TextColumn::make('group')
                    ->label('Group')
                    ->badge()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->getStateUsing(fn (Setting $record): string => $record->is_sensitive
                        ? '***masked***'
                        : self::previewValue($record))
                    ->wrap()
                    ->limit(60)
                    ->tooltip(fn (Setting $record): ?string => $record->is_sensitive ? 'Sensitive value disembunyikan' : null),

                IconColumn::make('is_readonly')
                    ->label('Read Only')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_sensitive')
                    ->label('Sensitive')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Group')
                    ->options(config('settings.groups', [])),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'array' => 'Array',
                        'json' => 'JSON',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    private static function previewValue(Setting $record): string
    {
        return match ($record->type) {
            'boolean' => $record->getValue() ? 'true' : 'false',
            'array', 'json' => json_encode($record->getValue(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            default => (string) $record->getValue(),
        };
    }
}
