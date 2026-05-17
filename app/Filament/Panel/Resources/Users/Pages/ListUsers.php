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
use Illuminate\Http\Request;

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
                        ->storeFiles(false) // Prevent auto-delete after action
                        ->required()
                        ->helperText('Format: JSON array dengan struktur sama seperti users.json. Max 5MB.'),

                    \Filament\Forms\Components\Toggle::make('skip_errors')
                        ->label('Lanjutkan meski ada error')
                        ->default(true)
                        ->helperText('Jika aktif, import akan terus berjalan meski ada baris yang gagal.'),
                ])
                ->action(function (array $data, ImportUsersFromJsonAction $importAction, Request $request): void {
                    try {
                        // Advanced logging - capture everything
                        \Log::debug('=== Import JSON Action Started ===');
                        \Log::debug('Form data keys: ' . implode(', ', array_keys($data)));
                        \Log::debug('Form data json_file type: ' . gettype($data['json_file']));
                        
                        if (is_object($data['json_file'])) {
                            \Log::debug('json_file is object: ' . get_class($data['json_file']));
                        }

                        $jsonContent = null;
                        $sourceFile = null;

                        // Strategy 1: Handle Livewire TemporaryUploadedFile object
                        if (isset($data['json_file'])) {
                            $fileData = $data['json_file'];
                            
                            // Check if it's a TemporaryUploadedFile object from Livewire
                            if (is_object($fileData)) {
                                \Log::debug('Strategy 1 - Handling object');
                                
                                // Check if it has getRealPath method (common for uploaded files)
                                if (method_exists($fileData, 'getRealPath')) {
                                    $filePath = $fileData->getRealPath();
                                    \Log::debug('Object has getRealPath, path: ' . $filePath);
                                    
                                    if (file_exists($filePath)) {
                                        $jsonContent = file_get_contents($filePath);
                                        $sourceFile = $filePath;
                                        \Log::debug('Successfully read from getRealPath', ['size' => strlen($jsonContent)]);
                                    } else {
                                        \Log::debug('getRealPath does not exist: ' . $filePath);
                                    }
                                }
                                
                                // If no getRealPath or file doesn't exist, try __toString
                                if (!$jsonContent && method_exists($fileData, '__toString')) {
                                    $filePath = (string)$fileData;
                                    \Log::debug('Object has __toString, path: ' . $filePath);
                                    
                                    if (file_exists($filePath)) {
                                        $jsonContent = file_get_contents($filePath);
                                        $sourceFile = $filePath;
                                        \Log::debug('Successfully read from __toString', ['size' => strlen($jsonContent)]);
                                    } else {
                                        \Log::debug('__toString path does not exist: ' . $filePath);
                                    }
                                }
                            }
                            // Check if it's a string path (fallback)
                            elseif (is_string($fileData)) {
                                \Log::debug('Strategy 2 - Handling string path: ' . $fileData);
                                
                                if (file_exists($fileData)) {
                                    $jsonContent = file_get_contents($fileData);
                                    $sourceFile = $fileData;
                                    \Log::debug('Successfully read from string path', ['size' => strlen($jsonContent)]);
                                } else {
                                    $disk = Storage::disk();
                                    if ($disk->exists($fileData)) {
                                        $jsonContent = $disk->get($fileData);
                                        $sourceFile = $fileData;
                                        \Log::debug('Successfully read from disk', ['size' => strlen($jsonContent)]);
                                    }
                                }
                            }
                        }

                        if (!$jsonContent) {
                            \Log::error('Failed to read JSON content', [
                                'data' => $data,
                                'fileDataType' => isset($data['json_file']) ? gettype($data['json_file']) : 'not set',
                                'fileDataClass' => isset($data['json_file']) && is_object($data['json_file']) ? get_class($data['json_file']) : 'N/A',
                            ]);
                            throw new \RuntimeException("Gagal membaca file JSON. Path: " . ($sourceFile ?? 'unknown'));
                        }

                        $userId = auth()->id();

                        if (!$userId) {
                            Notification::make()
                                ->title('Pengguna tidak terautentikasi')
                                ->danger()
                                ->send();
                            return;
                        }

                        \Log::debug("Proceeding with import", ['fileSize' => strlen($jsonContent), 'userId' => $userId]);

                        $usersData = json_decode($jsonContent, true);

                        if (!is_array($usersData)) {
                            \Log::error('Invalid JSON format', ['jsonError' => json_last_error_msg()]);
                            throw new \InvalidArgumentException('Format JSON tidak valid untuk import pengguna.');
                        }

                        \Log::debug("JSON decoded successfully", ['userCount' => count($usersData)]);

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

                            \Log::info('Import JSON - Completed with errors', [
                                'total' => $result['total'],
                                'created' => $result['created'],
                                'updated' => $result['updated'],
                                'failed' => $result['failed'],
                                'errorCount' => count($result['errors']),
                            ]);

                            Notification::make()
                                ->title('Import Pengguna Selesai dengan Catatan')
                                ->body($message . $warningMessage . "\n\nError:\n" . $errorDetails)
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($warningMessage !== '') {
                            \Log::info('Import JSON - Completed with warnings', [
                                'total' => $result['total'],
                                'created' => $result['created'],
                                'updated' => $result['updated'],
                            ]);

                            Notification::make()
                                ->title('Import Pengguna Selesai dengan Catatan')
                                ->body($message . $warningMessage)
                                ->warning()
                                ->send();

                            return;
                        }

                        \Log::info('Import JSON - Completed successfully', [
                            'total' => $result['total'],
                            'created' => $result['created'],
                            'updated' => $result['updated'],
                        ]);

                        Notification::make()
                            ->title('Import Pengguna Selesai')
                            ->body($message)
                            ->success()
                            ->send();

                        \Log::debug('=== Import JSON Action Completed Successfully ===');
                    } catch (\Throwable $e) {
                        \Log::error('=== Import JSON Action Failed ===', [
                            'exception' => get_class($e),
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Error saat import')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
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
