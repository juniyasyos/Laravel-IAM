<?php

namespace App\Filament\Panel\Resources\Settings\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setting Identity')
                    ->description('Key dan kategori menentukan posisi setting dalam struktur database settings.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key')
                                ->label('Setting Key')
                                ->required()
                                ->maxLength(255)
                                ->unique(
                                    table: Setting::class,
                                    column: 'key',
                                    ignoreRecord: true,
                                )
                                ->helperText('Gunakan dot notation, contoh: sso.ttl, iam.token_ttl, fortify.home.')
                                ->copyable()
                                ->columnSpanFull(),

                            Select::make('group')
                                ->label('Group')
                                ->required()
                                ->options(config('settings.groups', []))
                                ->searchable()
                                ->helperText('Kelompok utama untuk memudahkan filtering di layout Filament.'),

                            Select::make('type')
                                ->label('Value Type')
                                ->required()
                                ->options([
                                    'string' => 'String',
                                    'integer' => 'Integer',
                                    'boolean' => 'Boolean',
                                    'array' => 'Array',
                                    'json' => 'JSON',
                                ])
                                ->default('string')
                                ->live()
                                ->helperText('Menentukan cara value disimpan dan dibaca dari database.'),

                            Select::make('input_type')
                                ->label('Input Type')
                                ->required()
                                ->options([
                                    'text' => 'Text',
                                    'textarea' => 'Textarea',
                                    'number' => 'Number',
                                    'toggle' => 'Toggle',
                                    'select' => 'Select',
                                    'password' => 'Password',
                                    'json' => 'JSON',
                                ])
                                ->default('text')
                                ->helperText('Bentuk input yang akan dipakai saat edit di UI.'),
                        ]),
                    ]),

                Section::make('Value & Options')
                    ->description('Nilai utama setting dan opsi tambahan untuk validasi / select input.')
                    ->schema([
                        Textarea::make('value')
                            ->label('Value')
                            ->rows(5)
                            ->columnSpanFull()
                            ->helperText(fn (Get $get): string => $get('type') === 'json' || $get('type') === 'array'
                                ? 'Isi dengan JSON valid, contoh: {"enabled": true, "name": "IAM Home"}.'
                                : 'Isi value mentah yang akan disimpan di database.')
                            ->nullable(),

                        Textarea::make('select_options')
                            ->label('Select Options')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Satu opsi per baris atau pisahkan dengan koma. Hanya dipakai jika input type = select.')
                            ->formatStateUsing(fn ($state): string => self::listToText($state))
                            ->dehydrateStateUsing(fn ($state): ?array => self::textToList($state))
                            ->visible(fn (Get $get): bool => $get('input_type') === 'select')
                            ->nullable(),

                        Textarea::make('validation_rules')
                            ->label('Validation Rules')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Satu rule per baris atau pisahkan dengan koma, contoh: required, integer, min:60.')
                            ->formatStateUsing(fn ($state): string => self::listToText($state))
                            ->dehydrateStateUsing(fn ($state): ?array => self::textToList($state))
                            ->nullable(),
                    ]),

                Section::make('Metadata')
                    ->schema([
                        Grid::make(2)->schema([
                            Textarea::make('description')
                                ->label('Description')
                                ->rows(3)
                                ->columnSpanFull()
                                ->helperText('Catatan singkat untuk memudahkan admin memahami tujuan setting.'),

                            TextInput::make('environment')
                                ->label('Environment')
                                ->maxLength(64)
                                ->placeholder('production, staging, local')
                                ->helperText('Opsional. Bisa dipakai untuk memisahkan setting per environment.'),

                            TextInput::make('category')
                                ->label('Category')
                                ->maxLength(64)
                                ->placeholder('security, token, sync')
                                ->helperText('Kategori tambahan untuk grouping UI.'),

                            Toggle::make('is_readonly')
                                ->label('Read Only')
                                ->helperText('Jika aktif, value ini ditandai sebagai tidak boleh diubah lewat UI.'),

                            Toggle::make('is_sensitive')
                                ->label('Sensitive')
                                ->helperText('Menyembunyikan value saat ditampilkan di tabel dan log.'),
                        ]),
                    ]),
            ]);
    }

    private static function listToText(mixed $state): string
    {
        if (is_array($state)) {
            return implode("\n", $state);
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return implode("\n", $decoded);
            }

            return $state;
        }

        return '';
    }

    private static function textToList(mixed $state): ?array
    {
        if (is_array($state)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_string($item) ? trim($item) : $item,
                $state,
            ), static fn ($item) => $item !== ''));
        }

        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        $decoded = json_decode($state, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_string($item) ? trim($item) : $item,
                $decoded,
            ), static fn ($item) => $item !== ''));
        }

        $parts = preg_split('/[\r\n,]+/', $state) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
