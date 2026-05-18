<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FortifySettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'Fortify Settings';

    protected string $view = 'filament.panel.resources.settings.pages.group-settings';

    public ?array $data = [];

    public function mount(SettingService $settingService): void
    {
        $this->data = [
            'fortify_guard' => $settingService->get('fortify.guard', 'web'),
            'fortify_passwords' => $settingService->get('fortify.passwords', 'users'),
            'fortify_username' => $settingService->get('fortify.username', 'nip'),
            'fortify_email' => $settingService->get('fortify.email', 'nip'),
            'fortify_lowercase_usernames' => $settingService->get('fortify.lowercase_usernames', true),
            'fortify_home' => $settingService->get('fortify.home', '/dashboard'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Fortify Core')
                    ->description('Atur guard, broker, username field, dan perilaku login Fortify.')
                    ->columns(2)
                    ->schema([
                        Select::make('fortify_guard')
                            ->label('Guard')
                            ->options([
                                'web' => 'Web',
                                'api' => 'API',
                            ])
                            ->required(),
                        TextInput::make('fortify_passwords')
                            ->label('Password Broker')
                            ->maxLength(255),
                        TextInput::make('fortify_username')
                            ->label('Username Field')
                            ->maxLength(255),
                        TextInput::make('fortify_email')
                            ->label('Email Field')
                            ->maxLength(255),
                        Toggle::make('fortify_lowercase_usernames')
                            ->label('Lowercase Usernames')
                            ->columnSpanFull(),
                        TextInput::make('fortify_home')
                            ->label('Home Redirect')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(SettingService $settingService): void
    {
        $state = $this->form->getState();

        $settingService->set('fortify.guard', $state['fortify_guard'] ?? null);
        $settingService->set('fortify.passwords', $state['fortify_passwords'] ?? null);
        $settingService->set('fortify.username', $state['fortify_username'] ?? null);
        $settingService->set('fortify.email', $state['fortify_email'] ?? null);
        $settingService->set('fortify.lowercase_usernames', $state['fortify_lowercase_usernames'] ?? null);
        $settingService->set('fortify.home', $state['fortify_home'] ?? null);

        Notification::make()
            ->title('Fortify settings saved')
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
            'pageHeading' => 'Fortify Settings',
            'pageDescription' => 'Kelola guard, broker, username field, dan redirect default Fortify.',
            'submitLabel' => 'Save Fortify Values',
            'hasFields' => true,
        ];
    }
}