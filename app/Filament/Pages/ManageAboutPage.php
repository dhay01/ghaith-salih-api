<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translatable;
use App\Models\AboutPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The About page was the densest hardcoded prose in the app. Every section of it
 * is editable here, in the same order it appears on the site.
 */
class ManageAboutPage extends Page
{
    protected string $view = 'filament.pages.manage-about-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'About page';

    protected static ?string $title = 'About page';

    protected static ?int $navigationSort = 91;

    /** @var array<string, mixed> */
    public array $data = [];

    protected array $translatableColumns = [
        'hero_title',
        'hero_intro',
        'journey_title',
        'philosophy_quote',
        'philosophy_note',
        'gear_title',
    ];

    public function mount(): void
    {
        $this->form->fill($this->currentData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('About')->columnSpanFull()->tabs([
                    Tab::make('Hero')->schema([
                        Translatable::text('hero_title', 'Headline'),
                        Translatable::textarea('hero_intro', 'Intro', 3),
                        TagsInput::make('disciplines')
                            ->helperText('The chips under the intro, e.g. Landscape, Panorama, Gigapixel.'),
                        SpatieMediaLibraryFileUpload::make('hero_image')
                            ->collection('hero_image')
                            ->image()
                            ->label('Hero image'),
                    ]),

                    Tab::make('Journey')->schema([
                        Translatable::text('journey_title', 'Section title'),
                        Repeater::make('journey_paragraphs')
                            ->label('Paragraphs')
                            ->simple(Textarea::make('text')->rows(4)->required()),
                        Repeater::make('timeline')
                            ->columns(2)
                            ->schema([
                                TextInput::make('year')->required(),
                                TextInput::make('what')->label('What happened')->required(),
                            ]),
                    ]),

                    Tab::make('Philosophy')->schema([
                        Translatable::textarea('philosophy_quote', 'Pull quote', 3),
                        Translatable::textarea('philosophy_note', 'Note', 4),
                    ]),

                    Tab::make('Approach')->schema([
                        Repeater::make('approach')
                            ->columns(2)
                            ->schema([
                                TextInput::make('n')->label('Number')->placeholder('01')->required(),
                                TextInput::make('title')->required(),
                                Textarea::make('body')->rows(3)->required()->columnSpanFull(),
                            ]),
                    ]),

                    Tab::make('Gear')->schema([
                        Translatable::textarea('gear_title', 'Section title', 2),
                        Repeater::make('gear')
                            ->columns(2)
                            ->schema([
                                TextInput::make('label')->required(),
                                TextInput::make('value')->required(),
                            ]),
                        SpatieMediaLibraryFileUpload::make('gear_image')
                            ->collection('gear_image')
                            ->image()
                            ->label('Gear image'),
                    ]),
                ]),
            ])
            ->statePath('data')
            ->model(AboutPage::current());
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
        $about = AboutPage::current();

        $about->fill([
            'disciplines' => array_values($data['disciplines'] ?? []),
            'journey_paragraphs' => array_values($data['journey_paragraphs'] ?? []),
            'timeline' => array_values($data['timeline'] ?? []),
            'approach' => array_values($data['approach'] ?? []),
            'gear' => array_values($data['gear'] ?? []),
        ]);

        foreach ($this->translatableColumns as $column) {
            foreach (array_keys(Translatable::locales()) as $locale) {
                $value = $data["{$column}_{$locale}"] ?? null;

                if (filled($value)) {
                    $about->setTranslation($column, $locale, $value);
                }
            }
        }

        $about->save();

        // The media uploads bind to the model directly; saving the schema persists
        // them against the same record.
        $this->form->model($about)->saveRelationships();

        Notification::make()->title('About page saved')->success()->send();
    }

    /** @return array<string, mixed> */
    protected function currentData(): array
    {
        $about = AboutPage::current();

        $data = [
            'disciplines' => $about->disciplines ?? [],
            'journey_paragraphs' => $about->journey_paragraphs ?? [],
            'timeline' => $about->timeline ?? [],
            'approach' => $about->approach ?? [],
            'gear' => $about->gear ?? [],
        ];

        foreach ($this->translatableColumns as $column) {
            foreach (array_keys(Translatable::locales()) as $locale) {
                $data["{$column}_{$locale}"] = $about->getTranslation($column, $locale, false) ?: null;
            }
        }

        return $data;
    }
}
