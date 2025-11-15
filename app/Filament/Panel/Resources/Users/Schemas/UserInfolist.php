<?php

namespace App\Filament\Panel\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([

                        // ====================================
                        // PROFIL PENGGUNA
                        // ====================================
                        Section::make('Profil Pengguna')
                            ->icon('heroicon-m-user-circle')
                            ->description('Informasi identitas dasar dan status keaktifan pengguna.')
                            ->headerActions([])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Lengkap')
                                    ->weight('medium')
                                    ->icon('heroicon-m-identification')
                                    ->extraAttributes(['class' => 'text-gray-900 dark:text-gray-100 text-base']),

                                TextEntry::make('email')
                                    ->label('Alamat Email')
                                    ->copyable()
                                    ->copyMessage('Email berhasil disalin')
                                    ->icon('heroicon-m-envelope')
                                    ->extraAttributes(['class' => 'text-primary-600 dark:text-primary-400 font-medium']),

                                IconEntry::make('active')
                                    ->label('Status Akun')
                                    ->boolean()
                                    ->trueIcon('heroicon-m-check-badge')
                                    ->falseIcon('heroicon-m-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger')
                            ])
                            ->columns(2)
                            ->compact(),

                        // ====================================
                        // HAK AKSES & ROLE
                        // ====================================
                        Section::make('Hak Akses Aplikasi')
                            ->icon('heroicon-m-key')
                            ->description('Aplikasi yang dapat diakses pengguna beserta role dan paket aksesnya.')
                            ->schema([

                                // Profil Akses Global
                                TextEntry::make('access_profiles')
                                    ->label('Profil Akses Global')
                                    ->state(
                                        fn(User $record) =>
                                        $record->accessProfiles()->orderBy('name')->pluck('name')->all()
                                    )
                                    ->formatStateUsing(
                                        fn($state) =>
                                        empty($state) ? null : collect($state)->implode(' • ')
                                    )
                                    ->placeholder('Tidak ada profil akses')
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-m-shield-check'),

                                // Aplikasi yang dapat diakses
                                TextEntry::make('accessible_apps')
                                    ->label('Aplikasi yang bisa diakses')
                                    ->state(fn(User $record) => $record->accessibleApps())
                                    ->formatStateUsing(
                                        fn($state) =>
                                        empty($state)
                                            ? null
                                            : collect($state)->map(fn($v) => strtoupper($v))->implode(' • ')
                                    )
                                    ->placeholder('Tidak ada aplikasi terhubung')
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-m-cube')
                                    ->columnSpan(1),

                                // Role per aplikasi — KeyValue Entry
                                KeyValueEntry::make('roles_by_app_map')
                                    ->label('Role per Aplikasi')
                                    ->state(function (User $record) {
                                        $roles = $record->rolesByApp();
                                        if (empty($roles)) return null;

                                        return collect($roles)
                                            ->mapWithKeys(fn($roleList, $app) => [
                                                strtoupper($app) => implode(', ', $roleList)
                                            ])
                                            ->toArray();
                                    })
                                    ->placeholder('Belum ada role terhubung')
                                    ->keyLabel('Aplikasi')
                                    ->valueLabel('Role')
                                    ->columnSpanFull()
                                    ->hint('Role menentukan tingkat akses pengguna pada tiap aplikasi.'),
                            ])
                            ->columns(3)
                            ->collapsible(),

                        // ====================================
                        // KEAMANAN & META SISTEM
                        // ====================================
                        Section::make('Keamanan & Meta Sistem')
                            ->icon('heroicon-m-shield-exclamation')
                            ->description('Status keamanan akun, verifikasi, dan riwayat pengelolaan.')
                            ->schema([

                                TextEntry::make('email_verified_at')
                                    ->label('Verifikasi Email')
                                    ->dateTime()
                                    ->badge()
                                    ->placeholder('Belum diverifikasi')
                                    ->color(fn($state) => $state ? 'success' : 'warning')
                                    ->icon('heroicon-m-check')
                                    ->columnSpan(1),

                                TextEntry::make('two_factor_confirmed_at')
                                    ->label('Two-Factor Authentication')
                                    ->dateTime()
                                    ->placeholder('Tidak aktif')
                                    ->badge()
                                    ->color(fn($state) => $state ? 'success' : 'gray')
                                    ->icon('heroicon-m-lock-closed')
                                    ->columnSpan(1),

                                TextEntry::make('created_at')
                                    ->label('Ditambahkan Pada')
                                    ->dateTime()
                                    ->color('gray')
                                    ->icon('heroicon-m-calendar')
                                    ->columnSpan(1),

                                TextEntry::make('updated_at')
                                    ->label('Terakhir Diperbarui')
                                    ->dateTime()
                                    ->color('gray')
                                    ->icon('heroicon-m-clock')
                                    ->columnSpan(1),
                            ])
                            ->columns(2)
                            ->compact(),
                    ])
                    ->columnSpanFull()
            ]);
    }
}
