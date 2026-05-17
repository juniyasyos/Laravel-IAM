<?php

namespace App\Filament\Panel\Resources\Applications\Schemas;

use App\Domain\Iam\Models\Application;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Application Identity')
                            ->description('Identitas utama aplikasi yang digunakan oleh gateway SSO / IAM.')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Application Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('SIIMUT, Incident Reporter, Virtual Library, dst.')
                                        ->live(onBlur: true)
                                        ->columnSpanFull()
                                        ->afterStateUpdated(function (string $operation, $state, Set $set, Get $get): void {
                                            // Auto-generate app_key saat create jika belum diisi manual.
                                            if ($operation !== 'create') {
                                                return;
                                            }

                                            if (filled($get('app_key'))) {
                                                return;
                                            }

                                            $set('app_key', Str::slug((string) $state, '_'));
                                        }),


                                    Textarea::make('description')
                                        ->label('Description')
                                        ->rows(4)
                                        ->maxLength(1000)
                                        ->placeholder('Deskripsikan tujuan, owner, dan batasan akses aplikasi ini...')
                                        ->helperText('Contoh: Aplikasi untuk pelaporan indikator mutu RS Citra Husada, hanya untuk internal quality team.')
                                        ->columnSpanFull(),

                                    Grid::make(2)->schema([
                                        TextInput::make('app_key')
                                            ->label('App Key')
                                            ->required()
                                            ->maxLength(64)
                                            ->unique(
                                                table: Application::class,
                                                column: 'app_key',
                                                ignoreRecord: true,
                                            )
                                            ->dehydrateStateUsing(fn(string $state): string => Str::lower($state))
                                            ->rules(['regex:/^[a-z0-9\-_]+$/'])
                                            ->helperText('Lowercase, URL-safe, unik. Contoh: siimut_portal, incident_report.')
                                            ->prefixIcon('heroicon-m-finger-print')
                                            ->copyable()
                                            ->columnSpan(1)
                                            ->suffixAction(
                                                Action::make('generateAppKey')
                                                    ->icon('heroicon-m-sparkles')
                                                    ->tooltip('Generate app key dari nama aplikasi')
                                                    ->action(function (Set $set, Get $get): void {
                                                        $name = (string) $get('name');

                                                        $set('app_key', Str::slug(
                                                            $name !== '' ? $name : Str::random(8),
                                                            '_',
                                                        ));
                                                    })
                                            ),

                                    ]),

                                    ToggleButtons::make('enabled')
                                        ->label('Aplikasi Bisa Diakses?')
                                        ->options([
                                            1 => 'Aktif',
                                            0 => 'Nonaktif',
                                        ])
                                        ->colors([
                                            1 => 'success', // hijau
                                            0 => 'danger',  // merah
                                        ])
                                        ->icons([
                                            1 => 'heroicon-m-check-circle',
                                            0 => 'heroicon-m-x-circle',
                                        ])
                                        ->inline()
                                        ->columnSpan(1)
                                        ->default(1)
                                        ->helperText('Tentukan apakah aplikasi ini aktif dan bisa diakses oleh pengguna. Nonaktifkan jika maintenance.'),

                                ])->columnSpanFull(),
                            ]),

                        Section::make('Integration & Routing')
                            ->description('Konfigurasi endpoint yang digunakan saat proses login / callback.')
                            ->schema([
                                TextInput::make('redirect_uris')
                                    ->label('Redirect URIs')
                                    ->helperText('Opsional. Tekan Enter untuk menambah banyak redirect URI. Wajib HTTPS untuk produksi.')
                                    ->placeholder('https://app.example.com/oauth/callback'),

                                TextInput::make('callback_url')
                                    ->label('SSO Callback URL')
                                    ->required()
                                    ->url()
                                    ->maxLength(2048)
                                    ->placeholder('https://app.example.com/sso/callback')
                                    ->helperText('Endpoint utama yang menerima assertion / token dari SSO gateway.'),

                                TextInput::make('backchannel_url')
                                    ->label('Backchannel Base URL')
                                    ->url()
                                    ->maxLength(2048)
                                    ->nullable()
                                    ->placeholder('http://web:8000')
                                    ->helperText('Opsional: gunakan internal service hostname untuk sync/verify/logout backend-to-backend (misal: container alias). Jika kosong, callback_url digunakan.'),

                                TextInput::make('logo_url')
                                    ->label('Logo URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->nullable()
                                    ->placeholder('https://cdn.example.com/apps/siimut-logo.svg')
                                    ->helperText('Digunakan untuk menampilkan logo aplikasi di portal SSO.'),
                            ]),

                        Section::make('Security & Credentials')
                            ->description('Kredensial yang dipakai untuk mem-verifikasi integrasi aplikasi dengan SSO.')
                            ->schema([
                                TextInput::make('secret')
                                    ->label('SSO Shared Secret')
                                    ->password()
                                    ->revealable()
                                    ->copyable()
                                    ->nullable()
                                    ->maxLength(255)
                                    ->helperText('Shared secret yang digunakan partner aplikasi untuk menandatangani / memverifikasi request.')
                                    ->suffixAction(
                                        Action::make('generateSecret')
                                            ->icon('heroicon-m-key')
                                            ->tooltip('Generate strong random secret (aman untuk .env)')
                                            ->action(function (Set $set, Get $get): void {
                                                $appKey = (string) $get('app_key');

                                                $prefix = $appKey !== ''
                                                    ? Str::upper($appKey) . '_'
                                                    : '';

                                                // Secret kuat tapi aman dipakai di .env (tanpa simbol aneh)
                                                $secret = $prefix . Str::password(
                                                    length: 40,
                                                    letters: true,
                                                    numbers: true,
                                                    symbols: false,
                                                );

                                                $set('secret', $secret);
                                            })
                                    )
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
