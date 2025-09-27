<?php

namespace App\Filament\Panel\Resources\Applications\Schemas;

use App\Models\Application;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_key')
                    ->label('App Key')
                    ->required()
                    ->maxLength(255)
                    ->unique(table: Application::class, column: 'app_key', ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Toggle::make('enabled')
                    ->inline(false)
                    ->default(true),
                TagsInput::make('redirect_uris')
                    ->label('Redirect URIs')
                    ->helperText('Optional. Press Enter to add multiple redirect URIs.')
                    ->placeholder('https://app.example.com/oauth/callback')
                    ->nullable()
                    ->columnSpanFull(),
                TextInput::make('logo_url')
                    ->label('Logo URL')
                    ->url()
                    ->maxLength(255)
                    ->nullable(),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
