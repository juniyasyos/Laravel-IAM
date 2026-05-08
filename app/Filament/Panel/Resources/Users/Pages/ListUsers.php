<?php

namespace App\Filament\Panel\Resources\Users\Pages;

use App\Actions\ImportUsersFromJsonAction;
use App\Domain\Iam\Models\Application;
use App\Filament\Panel\Resources\Users\UserResource;
use App\Jobs\SyncApplicationUsers;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * OPTIMIZATION: Override to add eager loading and prevent N+1 queries
     */
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->withCommonRelations();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pengguna')
                ->icon('heroicon-m-plus')
                ->color('primary'),

            Action::make('importFromJson')
                ->label('Import Pengguna (JSON)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->schema([
                    \Filament\Forms\Components\FileUpload::make('json_file')
                        ->label('Upload File JSON')
                        ->acceptedFileTypes(['application/json'])
                        ->maxSize(5120) // 5MB
                        ->disk('s3')
                        ->directory('imports')
                        ->visibility('private')
                        ->required()
                        ->helperText('Format: JSON array dengan struktur sama seperti users.json. Max 5MB.'),

                    \Filament\Forms\Components\Toggle::make('skip_errors')
                        ->label('Lanjutkan meski ada error')
                        ->default(true)
                        ->helperText('Jika aktif, import akan terus berjalan meski ada baris yang gagal.'),
                ])
                ->action(function (array $data, ImportUsersFromJsonAction $importAction): void {
                    try {
                        $fileName = $data['json_file'];

                        $disk = Storage::disk('s3');

                        if (! $disk->exists($fileName)) {
                            Notification::make()
                                ->title('File tidak ditemukan di MinIO')
                                ->danger()
                                ->send();
                            return;
                        }

                        $userId = auth()->id();

                        if (! $userId) {
                            Notification::make()
                                ->title('Pengguna tidak terautentikasi')
                                ->danger()
                                ->send();
                            return;
                        }

                        $timestampedName = sprintf('imports/import_users_%s.json', now()->format('Ymd_His'));

                        // Copy to a predictable filename and remove the original hashed upload name
                        $disk->copy($fileName, $timestampedName);

                        if (! $disk->exists($timestampedName)) {
                            throw new \RuntimeException('File import pengguna tidak ditemukan di storage.');
                        }

                        $jsonContent = $disk->get($timestampedName);
                        $usersData = json_decode($jsonContent, true);

                        if (! is_array($usersData)) {
                            throw new \InvalidArgumentException('Format JSON tidak valid untuk import pengguna.');
                        }

                        $result = $importAction->execute($usersData);

                        $message = sprintf(
                            'Total: %d | Dibuat: %d | Diperbarui: %d | Gagal: %d',
                            $result['total'],
                            $result['created'],
                            $result['updated'],
                            $result['failed']
                        );

                        $warningLines = [];

                        if (! empty($result['warnings']['access_profiles_not_found'])) {
                            $warningLines[] = 'Access profile tidak ditemukan: ' . implode(', ', $result['warnings']['access_profiles_not_found']);
                        }

                        if (! empty($result['warnings']['unit_kerjas_not_found'])) {
                            $warningLines[] = 'Unit kerja tidak ditemukan: ' . implode(', ', $result['warnings']['unit_kerjas_not_found']);
                        }

                        $warningMessage = empty($warningLines)
                            ? ''
                            : "\n\nWarning:\n" . implode("\n", $warningLines);

                        if ($result['failed'] > 0) {
                            $errorDetails = collect($result['errors'])
                                ->map(fn($err) => sprintf(
                                    'Baris %d (%s): %s',
                                    $err['row'],
                                    $err['nip'],
                                    $err['error']
                                ))
                                ->join("\n");

                            Notification::make()
                                ->title('Import Pengguna Selesai dengan Catatan')
                                ->body($message . $warningMessage . "\n\nError:\n" . $errorDetails)
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($warningMessage !== '') {
                            Notification::make()
                                ->title('Import Pengguna Selesai dengan Catatan')
                                ->body($message . $warningMessage)
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Import Pengguna Selesai')
                            ->body($message)
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error saat import')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        if (Config::boolean('iam.imports.delete_source_after_import')) {
                            Storage::disk('s3')->delete($timestampedName ?? null);
                        }
                    }
                })
                ->modalHeading('Import Pengguna dari JSON')
                ->modalDescription('Upload file JSON berisi data pengguna untuk di-import. File sumber dapat dipertahankan atau dihapus sesuai konfigurasi import.')
                ->modalSubmitActionLabel('Import')
                ->modalWidth('2xl'),

            Action::make('syncFromApps')
                ->label('Sinkron pengguna (pilih aplikasi + role bundle)')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->authorize(fn() => false)
                ->schema([
                    \Filament\Forms\Components\CheckboxList::make('application_ids')
                        ->label('Aplikasi')
                        ->options(Application::query()->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->required(),

                    \Filament\Forms\Components\CheckboxList::make('profile_ids')
                        ->label('Role Bundles')
                        ->options(\App\Domain\Iam\Models\AccessProfile::active()->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->required(),

                    \Filament\Forms\Components\Select::make('sync_mode')
                        ->label('Mode sinkron')
                        ->options([
                            'auto' => 'Otomatis (role app ➜ access profile)',
                            'manual' => 'Manual (role map custom)',
                        ])
                        ->default('auto')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $applicationIds = $data['application_ids'] ?? [];
                    $profileIds = $data['profile_ids'] ?? [];

                    if (empty($applicationIds)) {
                        Notification::make()
                            ->title('Tidak ada aplikasi dipilih')
                            ->warning()
                            ->send();
                        return;
                    }

                    if (empty($profileIds)) {
                        Notification::make()
                            ->title('Tidak ada role bundle dipilih')
                            ->warning()
                            ->send();
                        return;
                    }

                    SyncApplicationUsers::dispatch($applicationIds, $profileIds);

                    Notification::make()
                        ->title('Job sinkron pengguna dijadwalkan')
                        ->success()
                        ->send();
                }),
        ];
    }
}
