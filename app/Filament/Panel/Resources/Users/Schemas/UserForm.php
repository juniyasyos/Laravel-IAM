<?php

namespace App\Filament\Panel\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->columnSpanFull()
                    ->schema([
                        // ====================================
                        // IDENTITAS PENGGUNA
                        // ====================================
                        Section::make('Identitas Pengguna')
                            ->description('Informasi dasar yang digunakan untuk mengenali pengguna di seluruh aplikasi IAM.')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Lengkap')
                                            ->placeholder('Mis. John Doe')
                                            ->required()
                                            ->maxLength(255)
                                            ->autocapitalize('words')
                                            ->autocomplete('name')
                                            ->helperText('Gunakan nama lengkap sesuai identitas atau standar internal organisasi.')
                                            ->hintIcon('heroicon-m-user-circle'),

                                        TextInput::make('email')
                                            ->label('Email Kerja')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(
                                                table: User::class,
                                                column: 'email',
                                                ignoreRecord: true,
                                            )
                                            ->placeholder('nama@perusahaan.com')
                                            ->autocomplete('email')
                                            ->suffixIcon('heroicon-m-envelope')
                                            ->helperText('Gunakan email resmi yang terdaftar di sistem IAM.')
                                            ->hintIcon('heroicon-m-at-symbol'),
                                    ]),
                            ]),

                        // ====================================
                        // KEAMANAN & KREDENSIAL
                        // ====================================
                        Section::make('Keamanan & Kredensial')
                            ->description('Atur password dan status keaktifan akun pengguna.')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('password')
                                            ->label('Password')
                                            ->password()
                                            ->revealable()
                                            ->maxLength(255)
                                            ->rule(Password::default())
                                            ->required(fn(string $operation): bool => $operation === 'create')
                                            ->dehydrated(fn(?string $state): bool => filled($state))
                                            ->placeholder(fn(string $operation): ?string => $operation === 'create'
                                                ? 'Minimal 8 karakter, kombinasi huruf & angka'
                                                : 'Kosongkan jika tidak ingin mengubah password')
                                            ->suffixIcon('heroicon-m-key')
                                            ->helperText(fn(string $operation): string => $operation === 'create'
                                                ? 'Wajib diisi saat membuat akun baru.'
                                                : 'Kosongkan field ini jika tidak ingin mengganti password.')
                                            ->hintIcon('heroicon-m-lock-closed'),

                                        Toggle::make('active')
                                            ->label('Status Akun Aktif')
                                            ->inline(false)
                                            ->default(true)
                                            ->nullable()
                                            ->helperText('Nonaktifkan jika pengguna sudah tidak boleh mengakses sistem IAM.')
                                            ->hintIcon('heroicon-m-exclamation-circle'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
