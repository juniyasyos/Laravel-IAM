<?php

namespace App\Filament\Panel\Resources\UnitKerjas\RelationManagers;

use App\Models\User;
use App\Observers\UserObserver;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

class UsersRelationUnitKerjaManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Split::make([
                    ImageColumn::make('avatar_url')
                        ->circular()
                        ->grow(false)
                        ->getStateUsing(fn($record) => $record->avatar_url ?: "https://ui-avatars.com/api/?name=" . urlencode($record->name)),
                    Stack::make([
                        TextColumn::make('name')
                            ->label(__('filament-forms::users.fields.name'))
                            ->searchable()
                            ->weight(FontWeight::Bold),
                    ])->alignStart()->space(1),
                    Stack::make([
                        TextColumn::make('roles.name')
                            ->label(__('filament-forms::users.fields.roles'))
                            ->searchable()
                            ->icon('heroicon-o-shield-check')
                            ->grow(false),
                        TextColumn::make('nip')
                            ->label(__('filament-forms::users.fields.email'))
                            ->icon('heroicon-m-finger-print')
                            ->searchable()
                            ->grow(false),
                    ])->alignStart()->visibleFrom('lg')->space(1)
                ])
            ])
            ->headerActions([
                AttachAction::make()
                    ->color('primary')
                    ->schema(fn() => [
                        Select::make('recordId')
                            ->options(function () {
                                $relatedIds = $this->getRelationship()->pluck('id')->toArray();

                                return User::whereNotIn('id', $relatedIds)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [
                                        $user->id => $user->name,
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->attachAnother(false)
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name'])
                    ->after(function ($record, array $data) {
                        $user = $record instanceof User
                            ? $record
                            : User::find($data['recordId'] ?? null);

                        if (! $user instanceof User) {
                            return;
                        }

                        app(UserObserver::class)->relationshipChanged($user, 'unit_kerja:attached');
                    }),
            ])
            ->actions([
                DetachAction::make()
                    ->requiresConfirmation()
                    ->after(function ($record) {
                        if (! $record instanceof User) {
                            return;
                        }

                        app(UserObserver::class)->relationshipChanged($record, 'unit_kerja:detached');
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->after(function ($records) {
                            collect($records)
                                ->filter(fn($record) => $record instanceof User)
                                ->each(fn(User $record) => app(UserObserver::class)
                                    ->relationshipChanged($record, 'unit_kerja:detached_bulk'));
                        }),
                ]),
            ]);
    }
}
