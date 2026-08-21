<?php

namespace App\Filament\Concerns;

/**
 * Bridges `{column}_{locale}` form fields and the JSON translation maps the models
 * actually store. Applied to a resource's Create and Edit pages.
 */
trait TranslatesFormData
{
    /** @return array<string> */
    protected function translatableColumns(): array
    {
        $model = static::getResource()::getModel();

        return (new $model)->translatable ?? [];
    }

    /** @return array<string> */
    protected function supportedLocales(): array
    {
        return array_keys(config('localization.supported', ['en' => []]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($this->translatableColumns() as $column) {
            $values = $data[$column] ?? [];

            // A model read through the translatable cast can hand back a plain
            // string for the active locale; normalise before splitting.
            if (is_string($values)) {
                $values = [config('localization.fallback', 'en') => $values];
            }

            foreach ($this->supportedLocales() as $locale) {
                $data["{$column}_{$locale}"] = $values[$locale] ?? null;
            }

            unset($data[$column]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->collapseTranslations($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->collapseTranslations($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function collapseTranslations(array $data): array
    {
        foreach ($this->translatableColumns() as $column) {
            $map = [];

            foreach ($this->supportedLocales() as $locale) {
                $key = "{$column}_{$locale}";

                if (array_key_exists($key, $data)) {
                    // Empty locales are dropped rather than stored as "", so a
                    // missing Arabic translation falls back instead of blanking.
                    if (filled($data[$key])) {
                        $map[$locale] = $data[$key];
                    }

                    unset($data[$key]);
                }
            }

            $data[$column] = $map;
        }

        return $data;
    }
}
