<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Pages;

use App\Filament\Panel\Resources\UnitKerjas\RelationManagers\UsersRelationUnitKerjaManager;
use App\Filament\Panel\Resources\UnitKerjas\UnitKerjaResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;

class EditUnitKerja extends EditRecord
{
    protected static string $resource = UnitKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RelationManagerAction::make('users')
                ->slideOver()
                ->icon('heroicon-o-user')
                ->record($this->getRecord())
                ->label(__('filament-forms::unit-kerja.actions.attach'))
                ->relationManager(UsersRelationUnitKerjaManager::make()),

            ActionGroup::make([
                ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->openUrlInNewTab(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->label('Aksi')
            ->icon('heroicon-o-ellipsis-vertical')
            ->button(),
        ];
    }
}
