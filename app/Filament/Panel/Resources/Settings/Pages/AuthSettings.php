<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthSettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'Authentication Settings';

    protected string $view = 'filament.panel.resources.settings.pages.group-settings';

    public ?array $data = [];

    public function mount(SettingService $settingService): void
    {
        $this->data = [
            'auth_defaults_guard' => $settingService->get('auth.defaults.guard', env('AUTH_GUARD', 'web')),
            'auth_defaults_passwords' => $settingService->get('auth.defaults.passwords', env('AUTH_PASSWORD_BROKER', 'users')),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Default Authentication')
                    ->description('Atur guard dan password broker default untuk aplikasi.')
                    ->columns(2)
                    ->schema([
                        Select::make('auth_defaults_guard')
                            ->label('Guard')
                            ->options([
                                'web' => 'Web',
                                'api' => 'API',
                            ])
                            ->required(),
                        TextInput::make('auth_defaults_passwords')
                            ->label('Password Broker')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function save(SettingService $settingService): void
    {
        $state = $this->form->getState();

        $settingService->set('auth.defaults.guard', $state['auth_defaults_guard'] ?? null);
        $settingService->set('auth.defaults.passwords', $state['auth_defaults_passwords'] ?? null);

        Notification::make()
            ->title('Authentication settings saved')
            ->body('Only setting values were updated. Keys remain locked.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'eyebrow' => 'Settings',
            'pageHeading' => 'Authentication Settings',
            'pageDescription' => 'Kelola default guard dan password broker yang dipakai aplikasi.',
            'submitLabel' => 'Save Auth Values',
            'hasFields' => true,
        ];
    }
}