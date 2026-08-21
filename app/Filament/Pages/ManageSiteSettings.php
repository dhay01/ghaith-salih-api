<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translatable;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Single-record editor for the site's identity, contact details and social links —
 * everything the footer and contact blocks used to hardcode.
 */
class ManageSiteSettings extends Page
{
    protected string $view = 'filament.pages.manage-site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->currentData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')->columns(2)->schema([
                    TextInput::make('name')->required(),
                    Translatable::text('tagline', 'Tagline'),
                    Translatable::text('studio', 'Studio location'),
                ]),

                Section::make('Contact')->columns(2)->schema([
                    TextInput::make('email')->email(),
                    TextInput::make('phone')->label('Phone (display)'),
                    TextInput::make('phone_href')
                        ->label('Phone (dial)')
                        ->helperText('Digits only, e.g. +9647705310152 — used for the tel: link.'),
                ]),

                Section::make('Byline')
                    ->description('Shown on blog posts, under the article and beside the title.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('author_name')->label('Name'),
                        TextInput::make('author_follow')->label('Follow URL'),
                        Translatable::text('author_location', 'Location'),
                        Translatable::textarea('author_bio', 'Bio', 2),
                        SpatieMediaLibraryFileUpload::make('author_photo')
                            ->collection('author_photo')
                            ->image()
                            ->label('Portrait')
                            ->columnSpanFull(),
                    ]),

                Section::make('Social links')->schema([
                    Repeater::make('socials')
                        ->label('')
                        ->columns(2)
                        ->reorderable()
                        ->schema([
                            TextInput::make('label')->required(),
                            TextInput::make('href')->label('URL')->required(),
                        ]),
                ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $site = SiteSetting::current();

        $site->fill([
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_href' => $data['phone_href'] ?? null,
            'socials' => array_values($data['socials'] ?? []),
            'author_name' => $data['author_name'] ?? null,
            'author_follow' => $data['author_follow'] ?? null,
        ]);

        foreach (['tagline', 'studio', 'author_location', 'author_bio'] as $column) {
            foreach (array_keys(Translatable::locales()) as $locale) {
                $value = $data["{$column}_{$locale}"] ?? null;

                if (filled($value)) {
                    $site->setTranslation($column, $locale, $value);
                }
            }
        }

        $site->save();

        // Persists the portrait upload against the same row.
        $this->form->model($site)->saveRelationships();

        Notification::make()->title('Site settings saved')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function currentData(): array
    {
        $site = SiteSetting::current();

        $data = [
            'name' => $site->name,
            'email' => $site->email,
            'phone' => $site->phone,
            'phone_href' => $site->phone_href,
            'socials' => $site->socials ?? [],
            'author_name' => $site->author_name,
            'author_follow' => $site->author_follow,
        ];

        foreach (['tagline', 'studio', 'author_location', 'author_bio'] as $column) {
            foreach (array_keys(Translatable::locales()) as $locale) {
                $data["{$column}_{$locale}"] = $site->getTranslation($column, $locale, false) ?: null;
            }
        }

        return $data;
    }
}
