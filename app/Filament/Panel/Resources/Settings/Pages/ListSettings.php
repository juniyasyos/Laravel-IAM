<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncDefinitions')
                ->label('Sync from Config')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->action(function (SettingService $settingService): void {
                    $count = $settingService->syncFromDefinitions();

                    Notification::make()
                        ->title('Settings synced')
                        ->body("{$count} setting definition(s) synchronised from config to database.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Add Setting')
                ->icon('heroicon-m-plus')
                ->color('primary'),
        ];
    }

    public function getTabs(): array
    {
        return [
            // 'all' => Tab::make('All'),

            'sso' => Tab::make('SSO')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('group', 'sso')),

            'iam' => Tab::make('IAM')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('group', 'iam')),

            'auth' => Tab::make('Auth')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('group', 'auth')),

            'fortify' => Tab::make('Fortify')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('group', 'fortify')),
        ];
    }
}
