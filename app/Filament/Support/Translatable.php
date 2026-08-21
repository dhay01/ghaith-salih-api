<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Renders one input per supported locale for a translatable column.
 *
 * The fields are named `{column}_{locale}` because the underlying column holds a
 * JSON map, not a string; TranslatesFormData on the Create/Edit page splits and
 * rejoins them. Authoring in Arabic therefore needs no schema change — the tab is
 * already there, waiting for content.
 */
class Translatable
{
    /** @return array<string, array<string, string>> */
    public static function locales(): array
    {
        return config('localization.supported', ['en' => ['native' => 'English', 'dir' => 'ltr']]);
    }

    public static function text(string $column, ?string $label = null): Tabs
    {
        return static::tabs($column, $label, fn (string $name) => TextInput::make($name));
    }

    public static function textarea(string $column, ?string $label = null, int $rows = 3): Tabs
    {
        return static::tabs($column, $label, fn (string $name) => Textarea::make($name)->rows($rows));
    }

    public static function rich(string $column, ?string $label = null): Tabs
    {
        return static::tabs($column, $label, fn (string $name) => RichEditor::make($name));
    }

    /** @param callable(string): Field $factory */
    protected static function tabs(string $column, ?string $label, callable $factory): Tabs
    {
        $label ??= str($column)->headline()->toString();
        $fallback = config('localization.fallback', 'en');

        $tabs = [];

        foreach (static::locales() as $locale => $meta) {
            $field = $factory("{$column}_{$locale}")
                ->label($label)
                ->required($locale === $fallback);

            if (($meta['dir'] ?? 'ltr') === 'rtl') {
                $field->extraInputAttributes(['dir' => 'rtl']);
            }

            $tabs[] = Tab::make($meta['native'] ?? $locale)->schema([$field]);
        }

        return Tabs::make($label)->tabs($tabs)->columnSpanFull();
    }
}
