<?php

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Filament\Concerns\TranslatesFormData;
use App\Filament\Resources\HeroSlides\HeroSlideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHeroSlide extends CreateRecord
{
    use TranslatesFormData;

    protected static string $resource = HeroSlideResource::class;
}
