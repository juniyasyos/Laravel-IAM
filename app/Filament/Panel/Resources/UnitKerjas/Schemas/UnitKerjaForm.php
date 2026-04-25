<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Schemas;

use Dom\Text;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnitKerjaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-forms::unit-kerja.form.unit.title'))
                    ->columnSpanFull()
                    ->description(__('filament-forms::unit-kerja.form.unit.description'))
                    ->schema([
                        TextInput::make('unit_name')
                            ->label(__('filament-forms::unit-kerja.fields.unit_name'))
                            ->placeholder(__('filament-forms::unit-kerja.form.unit.name_placeholder'))
                            ->helperText(__('filament-forms::unit-kerja.form.unit.helper_text'))
                            ->required()
                            ->unique('unit_kerja', 'unit_name', ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label(__('filament-forms::unit-kerja.fields.description'))
                            ->placeholder(__('filament-forms::unit-kerja.form.unit.description_placeholder'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
