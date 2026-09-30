<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A post's cover is the only picture the blog index, the home page and the article
 * header have. When it does not attach, all three silently fall back to a grey
 * placeholder and nothing anywhere says why.
 */
class PostCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function cover(string $name = 'cover.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1600, 900);
    }

    /** A real file on disk: a faked upload's temp file is cleaned up before the media is added. */
    protected function jpegOnDisk(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'postcover').'.jpg';
        $image = imagecreatetruecolor(1600, 900);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    public function test_a_cover_uploaded_while_creating_a_post_is_attached(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title_en' => 'Cover check',
                'excerpt_en' => 'An excerpt.',
                'image' => $this->cover(),
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->firstOrFail();

        $this->assertNotNull($post->getFirstMedia('image'), 'the cover never attached to the post');
        $this->assertNotNull($post->imageUrls()['thumb'] ?? null, 'the cover has no thumb conversion');
    }

    public function test_the_cover_shows_on_the_edit_page_and_survives_saving_it(): void
    {
        $post = Post::create(['title' => ['en' => 'Edit check'], 'is_published' => true]);
        $post->addMedia($this->jpegOnDisk())->toMediaCollection('image');

        $page = Livewire::test(EditPost::class, ['record' => $post->getRouteKey()]);

        $this->assertNotEmpty($page->get('data.image'), 'the edit page shows no cover even though one is attached');

        $page->call('save')->assertHasNoFormErrors();

        $this->assertNotNull($post->fresh()->getFirstMedia('image'), 'saving the post removed its cover');
    }

    public function test_the_api_advertises_the_cover_to_the_blog_index_and_the_article(): void
    {
        $post = Post::create(['title' => ['en' => 'Api check'], 'is_published' => true, 'published_on' => now()]);
        $post->addMedia($this->jpegOnDisk())->toMediaCollection('image');

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonPath('data.0.images.thumb', fn ($url) => filled($url));

        $this->getJson("/api/posts/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.images.preview', fn ($url) => filled($url));
    }
}
