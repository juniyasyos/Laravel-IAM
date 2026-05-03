<?php

namespace App\Filament\Panel\Resources\Users\Pages;

use App\Domain\Iam\Models\Application;
use App\Filament\Panel\Resources\Users\UserResource;
use App\Jobs\SyncApplicationUsers;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Actions\ImportUsersFromJsonAction;
use Illuminate\Support\Facades\Storage;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

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
                ->action(function (array $data): void {
                    try {
                        $fileName = $data['json_file'];

                        if (!Storage::disk('s3')->exists($fileName)) {
                            Notification::make()
                                ->title('File tidak ditemukan di MinIO')
                                ->danger()
                                ->send();
                            return;
                        }

                        $jsonContent = Storage::disk('s3')->get($fileName);
                        $usersData = json_decode($jsonContent, true);

                        if (!is_array($usersData)) {
                            Notification::make()
                                ->title('Format JSON tidak valid')
                                ->body('File harus berisi array JSON.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $action = new ImportUsersFromJsonAction();
                        $result = $action->execute($usersData);

                        // Buat notifikasi hasil
                        $title = "Import Pengguna Selesai";
                        $message = sprintf(
                            "Total: %d | Dibuat: %d | Diperbarui: %d | Gagal: %d",
                            $result['total'],
                            $result['created'],
                            $result['updated'],
                            $result['failed']
                        );

                        if ($result['failed'] > 0) {
                            $errorDetails = collect($result['errors'])
                                ->map(fn($err) => sprintf(
                                    "Baris %d (%s): %s",
                                    $err['row'],
                                    $err['nip'],
                                    $err['error']
                                ))
                                ->join("\n");

                            Notification::make()
                                ->title($title)
                                ->body($message . "\n\nError:\n" . $errorDetails)
                                ->warning()
                                ->duration(10)
                                ->send();
                        } else {
                            Notification::make()
                                ->title($title)
                                ->body($message)
                                ->success()
                                ->send();
                        }

                        // Cleanup dari MinIO
                        Storage::disk('s3')->delete($fileName);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error saat import')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalHeading('Import Pengguna dari JSON')
                ->modalDescription('Upload file JSON berisi data pengguna untuk di-import. Data disimpan di MinIO dan akan dihapus setelah import selesai.')
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
