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
                ->label('Sinkron semua pengguna')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action(function (): void {
                    $apps = Application::all();
                    if ($apps->isEmpty()) {
                        Notification::make()
                            ->title('No applications found')
                            ->warning()
                            ->send();
                        return;
                    }

                    foreach ($apps as $app) {
                        SyncApplicationUsers::dispatch($app);
                    }

                    Notification::make()
                        ->title('User sync jobs queued')
                        ->success()
                        ->send();
                }),
        ];
    }
}
