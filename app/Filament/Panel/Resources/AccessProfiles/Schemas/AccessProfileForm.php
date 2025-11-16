<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Schemas;

use App\Domain\Iam\Models\ApplicationRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AccessProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Profile Identity')
                            ->description('Identitas profil akses yang digunakan untuk mengelompokkan hak akses lintas aplikasi.')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Profile Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('Contoh: Quality Team, Manajemen RS, IT Support')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (string $operation, $state, Set $set, Get $get): void {
                                            // Auto generate slug hanya saat create dan slug masih kosong.
                                            if ($operation !== 'create') {
                                                return;
                                            }

                                            if (filled($get('slug'))) {
                                                return;
                                            }

                                            $set('slug', Str::slug((string) $state, '_'));
                                        }),

                                    TextInput::make('slug')
                                        ->label('Profile Slug')
                                        ->required()
                                        ->maxLength(64)
                                        ->rules(['regex:/^[a-z0-9\-_]+$/'])
                                        ->placeholder('quality_team, manajemen_rs, it_support')
                                        ->helperText('Dipakai internal oleh sistem IAM. Hanya huruf kecil, angka, dash, dan underscore.')
                                        ->dehydrateStateUsing(fn(string $state): string => Str::lower($state))
                                        ->suffixIcon('heroicon-m-finger-print'),
                                ]),

                                Grid::make(2)->schema([
                                    Toggle::make('is_system')
                                        ->label('System Profile')
                                        ->default(false)
                                        ->helperText('Jika aktif, profil ini dianggap kritikal dan biasanya tidak dihapus / diubah oleh user biasa.'),

                                    Toggle::make('is_active')
                                        ->label('Active')
                                        ->default(true)
                                        ->helperText('Nonaktifkan untuk menghentikan pemakaian profil tanpa menghapus mapping user & role.'),
                                ]),
                            ]),

                        Section::make('Roles & Permissions')
                            ->description('Mapping profile ini ke role-role aplikasi. Satu profile bisa punya banyak role lintas aplikasi.')
                            ->schema([
                                Select::make('roles')
                                    ->label('Assigned Roles (Application — Role)')
                                    ->relationship(
                                        name: 'roles',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn(Builder $query) => $query->with('application'),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn(ApplicationRole $record): string => ($record->application?->name ?? 'App ID: ' . $record->application_id)
                                            . ' — '
                                            . $record->name
                                    )
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Pilih kombinasi aplikasi + role yang akan dibungkus oleh profile ini.')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Metadata & Description')
                            ->description('Dokumentasi singkat mengenai tujuan, ruang lingkup, dan siapa yang menggunakan profile ini.')
                            ->schema([
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(4)
                                    ->maxLength(1000)
                                    ->placeholder('Contoh: Profile untuk tim mutu RS, memiliki akses ke SIIMUT (admin) dan Incident Reporter (viewer).')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
