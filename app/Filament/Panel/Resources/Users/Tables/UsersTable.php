<?php

namespace App\Filament\Panel\Resources\Users\Tables;

use App\Filament\Panel\Resources\Users\RelationManagers\AccessProfilesRelationManager;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use STS\FilamentImpersonate\Actions\Impersonate as ImpersonateTableAction;
use App\Jobs\SyncApplicationUsers;
use App\Domain\Iam\Models\Application;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Manajemen Pengguna')
            ->description('Kelola akun IAM, hak akses aplikasi, dan status keamanan pengguna.')
            ->defaultSort('updated_at', 'desc')
            ->poll('2s')
            ->paginated([10, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->searchPlaceholder('Cari nama, NIP, atau email pengguna...')
            ->columns([

                // Nama + nip + email
                TextColumn::make('name')
                    ->label('Pengguna')
                    ->weight('semibold')
                    ->description(fn(User $record) => $record->nip)
                    ->icon('heroicon-m-user-circle')
                    ->searchable(['name', 'nip', 'email'])
                    ->sortable()
                    ->toggleable(),

                // UNIT KERJA
                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->getStateUsing(function (User $record): ?string {
                        $unitKerjas = $record->unitKerjas()->pluck('unit_name')->toArray() ?? [];

                        if (empty($unitKerjas)) {
                            return null;
                        }

                        return collect($unitKerjas)
                            ->implode(', ');
                    })
                    ->weight('semibold')
                    ->color('slate')
                    ->tooltip('Unit kerja yang menjadi tempat tugas pengguna.')
                    ->wrap()
                    ->placeholder('Belum ada unit kerja')
                    ->toggleable(),

                // DAFTAR APLIKASI YANG BISA DIAKSES
                TextColumn::make('accessible_apps')
                    ->label('Role Bundles')
                    ->getStateUsing(function (User $record): ?string {
                        $apps = $record->accessProfiles()->pluck('name')->toArray() ?? [];

                        if (empty($apps)) {
                            return null;
                        }

                        return collect($apps)
                            ->map(fn(string $appKey) => strtoupper($appKey))
                            ->implode(' • ');
                    })
                    ->badge()
                    ->color('info')
                    ->tooltip('Daftar aplikasi yang dapat diakses pengguna melalui IAM.')
                    ->wrap()
                    ->placeholder('Tidak ada akses aplikasi')
                    ->toggleable(),

                // RINGKASAN IAM (jumlah aplikasi & profil)
                TextColumn::make('iam_summary')
                    ->label('Ringkasan IAM')
                    ->getStateUsing(function (User $record): ?string {
                        $apps = $record->accessibleApps() ?? [];
                        $profilesCount = $record->accessProfiles()->count();

                        if (empty($apps) && $profilesCount === 0) {
                            return null;
                        }

                        return sprintf('%d aplikasi • %d profil akses', count($apps), $profilesCount);
                    })
                    ->badge()
                    ->color('primary')
                    ->tooltip('Ringkasan jumlah aplikasi terhubung dan profil akses global pengguna.')
                    ->toggleable(isToggledHiddenByDefault: true),

                // STATUS AKUN
                ToggleColumn::make('active')
                    ->label('Status')
                    ->onColor('success')
                    ->offColor('secondary')
                    ->onIcon('heroicon-m-check-badge')
                    ->offIcon('heroicon-m-no-symbol')
                    ->afterStateUpdated(fn(User $record, bool $state) => $record->refresh())
                    ->sortable()
                    ->tooltip(fn(User $record) => $record->active ? 'Akun aktif' : 'Akun nonaktif')
                    ->toggleable(),

                // LOGIN AKTIF
                TextColumn::make('session_active')
                    ->label('Login Aktif')
                    ->getStateUsing(function (User $record) {
                        if (! $record->hasActiveSession()) {
                            return 'Tidak login';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();

                        return ($start && $end && now()->between($start, $end))
                            ? 'Sedang login'
                            : 'Sesi berakhir';
                    })
                    ->description(function (User $record) {
                        if (! $record->hasActiveSession()) {
                            return 'Tidak ada sesi login aktif';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();

                        if (! $start || ! $end) {
                            return 'Tidak ada sesi login aktif';
                        }

                        if (! now()->between($start, $end)) {
                            return 'Sesi sudah berakhir';
                        }

                        $remainingMinutes = now()->diffInMinutes($end, false);
                        $remainingText = $remainingMinutes > 0
                            ? ($remainingMinutes >= 60
                                ? intval($remainingMinutes / 60) . ' jam ' . ($remainingMinutes % 60 ? ($remainingMinutes % 60) . ' menit' : '')
                                : $remainingMinutes . ' menit')
                            : 'kurang dari 1 menit';

                        return "{$start->format('H:i')} - {$end->format('H:i')} (expired dalam waktu {$remainingText})";
                    })
                    ->tooltip(function (User $record) {
                        if (! $record->hasActiveSession()) {
                            return 'Tidak ada sesi login aktif';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();

                        if (! $start || ! $end) {
                            return 'Tidak ada sesi login aktif';
                        }

                        return "{$start->format('H:i')} - {$end->format('H:i')}";
                    })
                    ->toggleable(),

                // MFA / TWO FACTOR
                IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->boolean()
                    ->getStateUsing(fn(User $record) => ! empty($record->two_factor_secret ?? null))
                    ->trueIcon('heroicon-m-lock-closed')
                    ->falseIcon('heroicon-m-lock-open')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn(User $record) => ! empty($record->two_factor_secret ?? null) ? 'MFA aktif' : 'MFA tidak aktif')
                    ->toggleable(isToggledHiddenByDefault: true),

                // UPDATED AT
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Status akun')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->placeholder('Semua'),

                TernaryFilter::make('mfa_enabled')
                    ->label('MFA')
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('two_factor_secret'),
                        false: fn(Builder $query) => $query->whereNull('two_factor_secret'),
                        blank: fn(Builder $query) => $query,
                    )
                    ->trueLabel('MFA aktif')
                    ->falseLabel('MFA tidak aktif')
                    ->placeholder('Semua'),

                Filter::make('updated_between')
                    ->label('Rentang pembaruan')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari tanggal')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Sampai tanggal')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $q, $date) => $q->whereDate('updated_at', '>=', $date))
                            ->when($data['until'] ?? null, fn(Builder $q, $date) => $q->whereDate('updated_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['from'])) {
                            $indicators[] = 'Diperbarui sejak ' . $data['from'];
                        }

                        if (! empty($data['until'])) {
                            $indicators[] = 'Diperbarui hingga ' . $data['until'];
                        }

                        return $indicators;
                    }),

                Filter::make('never_logged_in')
                    ->label('Belum pernah login')
                    ->query(fn(Builder $query) => $query->whereNull('last_login_at')),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                // ImpersonateTableAction::make()
                //     ->label('Impersonate')
                //     ->icon('heroicon-m-arrow-right-on-rectangle')
                //     ->visible(fn(User $record) => Auth::id() !== $record->id),
                RelationManagerAction::make()
                    ->label('Manage Role Bundles')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->slideOver()
                    ->relationManager(AccessProfilesRelationManager::make()),
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Detail')
                        ->icon('heroicon-m-eye'),

                    EditAction::make()
                        ->label('Edit'),

                    Action::make('terminateSession')
                        ->label('Hapus Sesi')
                        ->icon('heroicon-m-arrow-left-end-on-rectangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(User $record) => $record->hasActiveSession())
                        ->action(function (User $record) {
                            $deleted = $record->terminateSessions();

                            Notification::make()
                                ->title($deleted ? 'Sesi login pengguna dihapus' : 'Tidak ditemukan sesi aktif')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Aktifkan')
                        ->icon('heroicon-m-bolt')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn(Collection $records) => $records->each->update(['active' => true])),

                    BulkAction::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon('heroicon-m-no-symbol')
                        ->color('secondary')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn(Collection $records) => $records->each->update(['active' => false])),

                    DeleteBulkAction::make()
                        ->label('Hapus')
                        ->icon('heroicon-m-trash'),
                ]),
            ])
            ->emptyStateIcon('heroicon-m-user-group')
            ->emptyStateHeading('Belum ada user')
            ->emptyStateDescription('Tambahkan user baru atau ubah filter pencarian untuk melihat data.');
    }
}
