<?php

namespace App\Filament\Panel\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(table: User::class, column: 'email', ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Toggle::make('active')
                    ->label('Active')
                    ->inline(false)
                    ->default(true)
                    ->nullable(),
            ]);
    }
}
