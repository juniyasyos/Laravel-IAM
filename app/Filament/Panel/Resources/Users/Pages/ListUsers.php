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

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('syncFromApps')
                ->label('Sinkron pengguna (pilih aplikasi + role bundle)')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
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
