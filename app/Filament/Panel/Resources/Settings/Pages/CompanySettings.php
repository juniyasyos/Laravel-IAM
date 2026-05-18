<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanySettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'Company Settings';

    protected string $view = 'filament.panel.resources.settings.pages.company-settings';

    public ?array $data = [];

    public function mount(SettingService $settingService): void
    {
        $this->data = [
            'company_name' => $settingService->get('company.name', 'RS Citra Husada Sejahtera'),
            'company_tagline' => $settingService->get('company.tagline', 'Melayani dengan Hati dan Profesionalisme'),
            'company_logo' => $settingService->get('company.logo', '/images/company/logo.png'),
            'company_address' => $settingService->get('company.address', 'Jl. Raya Kesehatan No. 123, Kecamatan Sejahtera'),
            'company_phone' => $settingService->get('company.phone', '(0331) 123456'),
            'company_email' => $settingService->get('company.email', 'info@citrahusada.co.id'),
            'company_website' => $settingService->get('company.website', 'https://citrahusada.co.id'),
            'company_city' => $settingService->get('company.city', 'Jember'),
            'company_postal_code' => $settingService->get('company.postal_code', '68121'),
            'company_director_name' => $settingService->get('company.director_name', 'dr. Andi Pratama, M.Kes'),
            'company_director_title' => $settingService->get('company.director_title', 'Direktur Utama'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Profil Perusahaan')
                    ->description('Kelola identitas dan informasi utama perusahaan.')
                    ->icon('heroicon-o-building-office')
                    ->schema([

                        Fieldset::make('Identitas Utama')
                            ->schema([
                                TextInput::make('company_name')
                                    ->label('Nama Perusahaan')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: PT Citra Husada Sejahtera')
                                    ->columnSpanFull(),

                                TextInput::make('company_tagline')
                                    ->label('Tagline')
                                    ->maxLength(255)
                                    ->placeholder('Contoh: Solusi Kesehatan Terpercaya')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Fieldset::make('Branding')
                            ->schema([
                                TextInput::make('company_logo')
                                    ->label('Logo (URL)')
                                    ->type('url')
                                    ->placeholder('https://...')
                                    ->suffixIcon('heroicon-m-photo')
                                    ->columnSpanFull(),
                            ]),

                        Fieldset::make('Alamat')
                            ->schema([
                                Textarea::make('company_address')
                                    ->label('Alamat Lengkap')
                                    ->rows(3)
                                    ->placeholder('Jl. Contoh No.123...')
                                    ->columnSpanFull(),

                                TextInput::make('company_city')
                                    ->label('Kota'),

                                TextInput::make('company_postal_code')
                                    ->label('Kode Pos'),
                            ])
                            ->columns(2),

                        Fieldset::make('Kontak')
                            ->schema([
                                TextInput::make('company_phone')
                                    ->label('Telepon'),
                                    // ->tel()
                                    // ->prefix('+62'),

                                TextInput::make('company_email')
                                    ->label('Email')
                                    ->email(),

                                TextInput::make('company_website')
                                    ->label('Website')
                                    ->url()
                                    ->prefix('https://'),
                            ])
                            ->columns(3),

                    ])
            ]);
    }

    public function save(SettingService $settingService): void
    {
        $state = $this->form->getState();

        $settingService->set('company.name', $state['company_name'] ?? null);
        $settingService->set('company.tagline', $state['company_tagline'] ?? null);
        $settingService->set('company.logo', $state['company_logo'] ?? null);
        $settingService->set('company.address', $state['company_address'] ?? null);
        $settingService->set('company.phone', $state['company_phone'] ?? null);
        $settingService->set('company.email', $state['company_email'] ?? null);
        $settingService->set('company.website', $state['company_website'] ?? null);
        $settingService->set('company.city', $state['company_city'] ?? null);
        $settingService->set('company.postal_code', $state['company_postal_code'] ?? null);

        Notification::make()
            ->title('Company settings saved')
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
            'hasFields' => true,
        ];
    }
}
