<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A category is put on the home page with one switch. It used to take a tile
 * width out of twelve and a ratio, which nobody could be expected to work out.
 */
class CategoryHomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function category(string $slug, array $attributes = []): Category
    {
        return Category::create([
            'type' => Category::TYPE_WORK,
            'slug' => $slug,
            'name' => ['en' => ucfirst($slug)],
            'position' => 1,
        ] + $attributes);
    }

    public function test_the_switch_puts_a_category_on_the_home_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $category = $this->category('landscape');

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['show_on_home' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($category->fresh()->show_on_home);
        $this->getJson('/api/categories?type=work')->assertOk()->assertJsonPath('data.0.show_on_home', true);
    }

    public function test_categories_that_had_a_tile_width_stay_on_the_home_page(): void
    {
        $migration = require database_path('migrations/2026_10_03_130000_add_show_on_home_to_categories.php');
        $migration->down();

        $this->category('wide', ['grid_span' => 7]);
        $this->category('narrow', ['grid_span' => 1]);
        $this->category('left-out');

        $migration->up();

        $featured = Category::query()->pluck('show_on_home', 'slug')->all();

        $this->assertSame(['wide' => true, 'narrow' => true, 'left-out' => false], $featured);
    }
}
