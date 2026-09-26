<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageAboutPage;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\HeroSlides\Pages\EditHeroSlide;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An upload field that does not show what is already stored reads as empty, so
 * the file looks lost and cannot be replaced — there is nothing to delete.
 *
 * Filament hydrates these fields from the media collection, so what is asserted
 * here is that opening the page finds the existing file, for every upload in the
 * panel rather than the one that was noticed.
 */
class UploadPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function png(int $w = 600, int $h = 400): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upload').'.png';
        $image = imagecreatetruecolor($w, $h);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** The field state is keyed by media uuid, so a non-empty array is the file showing. */
    protected function assertFieldHoldsAFile($component, string $field): void
    {
        $state = $component->get("data.{$field}");

        $this->assertIsArray($state, "{$field} should hydrate to an array of stored files");
        $this->assertNotEmpty(
            $state,
            "{$field} renders empty even though a file is attached, so it cannot be seen or replaced",
        );
    }

    public function test_the_logo_shows_when_the_settings_page_is_opened(): void
    {
        $site = SiteSetting::current();
        $site->addMedia($this->png(900, 300))->toMediaCollection('logo');

        $this->actingAs($this->admin());

        $this->assertFieldHoldsAFile(Livewire::test(ManageSiteSettings::class), 'logo');
    }

    public function test_the_author_portrait_shows_when_the_settings_page_is_opened(): void
    {
        $site = SiteSetting::current();
        $site->addMedia($this->png())->toMediaCollection('author_photo');

        $this->actingAs($this->admin());

        $this->assertFieldHoldsAFile(Livewire::test(ManageSiteSettings::class), 'author_photo');
    }

    public function test_the_about_page_images_show_when_it_is_opened(): void
    {
        $about = \App\Models\AboutPage::current();
        $about->addMedia($this->png())->toMediaCollection('hero_image');
        $about->addMedia($this->png())->toMediaCollection('gear_image');

        $this->actingAs($this->admin());
        $page = Livewire::test(ManageAboutPage::class);

        $this->assertFieldHoldsAFile($page, 'hero_image');
        $this->assertFieldHoldsAFile($page, 'gear_image');
    }

    public function test_a_category_image_shows_when_editing(): void
    {
        $category = Category::create([
            'type' => Category::TYPE_WORK,
            'slug' => 'preview-check',
            'name' => ['en' => 'Preview check'],
            'position' => 1,
        ]);
        $category->addMedia($this->png())->toMediaCollection('image');
        $category->refresh();

        $this->actingAs($this->admin());

        $this->assertFieldHoldsAFile(
            Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()]),
            'image',
        );
    }

    public function test_a_hero_slide_image_shows_when_editing(): void
    {
        $slide = HeroSlide::create(['alt' => ['en' => 'Preview check'], 'position' => 0]);
        $slide->addMedia($this->png())->toMediaCollection('image');

        $this->actingAs($this->admin());

        $this->assertFieldHoldsAFile(
            Livewire::test(EditHeroSlide::class, ['record' => $slide->getKey()]),
            'image',
        );
    }

    public function test_a_stored_file_can_be_removed_and_the_removal_sticks(): void
    {
        $site = SiteSetting::current();
        $site->addMedia($this->png(900, 300))->toMediaCollection('logo');

        $this->actingAs($this->admin());

        Livewire::test(ManageSiteSettings::class)
            ->fillForm(['logo' => []])
            ->call('save');

        $this->assertCount(
            0,
            SiteSetting::current()->fresh()->getMedia('logo'),
            'clearing the field should delete the stored file, not leave it orphaned',
        );
    }
}
