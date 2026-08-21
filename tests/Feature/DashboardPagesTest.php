<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every content surface an admin needs should render, not 500. */
class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    public static function pageProvider(): array
    {
        return [
            'photos' => ['/admin/photos'],
            'photo create' => ['/admin/photos/create'],
            'categories' => ['/admin/categories'],
            'category create' => ['/admin/categories/create'],
            'posts' => ['/admin/posts'],
            'post create' => ['/admin/posts/create'],
            'hero slides' => ['/admin/hero-slides'],
            'site settings' => ['/admin/manage-site-settings'],
            'about page' => ['/admin/manage-about-page'],
            'workshops' => ['/admin/workshops'],
            'reservations' => ['/admin/reservations'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageProvider')]
    public function test_dashboard_page_renders(string $path): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get($path)
            ->assertSuccessful();
    }
}
