<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's Builder saves every block as {type, data: {...}}; the article
 * page reads them flat ({type, paragraphs}, {type, text}, ...). A body that
 * reaches the page still nested renders as nothing: the article showed a cover
 * slot and a tag and no text at all.
 */
class PostBodyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('media-library.disk_name'));
    }

    /** @return array<int, array<string, mixed>> */
    protected function bodyOf(array $blocks): array
    {
        $post = Post::create([
            'title' => ['en' => 'Body check'],
            'body' => $blocks,
            'is_published' => true,
            'published_on' => now(),
        ]);

        return $this->getJson("/api/posts/{$post->slug}")->assertOk()->json('data.body');
    }

    public function test_blocks_written_in_the_dashboard_reach_the_article_flat(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title_en' => 'Written in the dashboard',
                'is_published' => true,
                'body' => [
                    ['type' => 'text', 'data' => ['paragraphs' => "First paragraph.\n\nSecond paragraph."]],
                    ['type' => 'heading', 'data' => ['text' => 'A heading']],
                    ['type' => 'quote', 'data' => ['text' => 'A pull quote.']],
                    ['type' => 'figure', 'data' => [
                        'path' => [UploadedFile::fake()->image('inline.jpg', 1200, 800)],
                        'ratio' => '3 / 2',
                        'caption' => 'An inline photo',
                    ]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->firstOrFail();
        $body = collect($this->getJson("/api/posts/{$post->slug}")->assertOk()->json('data.body'))->keyBy('type');

        // Keyed by type: the test harness uploads the figure's file ahead of the
        // other fields, which shuffles where the block lands, not what it holds.
        $this->assertEqualsCanonicalizing(['text', 'heading', 'quote', 'figure'], $body->keys()->all());
        $this->assertSame(['First paragraph.', 'Second paragraph.'], $body['text']['paragraphs']);
        $this->assertSame('A heading', $body['heading']['text']);
        $this->assertSame('A pull quote.', $body['quote']['text']);
        $this->assertSame('An inline photo', $body['figure']['caption']);
        $this->assertSame('3 / 2', $body['figure']['ratio']);
        $this->assertStringContainsString('posts/', $body['figure']['src'], 'the inline photo has no URL');
        $this->assertArrayNotHasKey('data', $body['text']);
    }

    public function test_a_body_written_flat_still_renders(): void
    {
        $body = $this->bodyOf([
            ['type' => 'text', 'paragraphs' => ['One.', 'Two.']],
            ['type' => 'heading', 'text' => 'Flat heading'],
            ['type' => 'figure', 'path' => 'posts/flat.jpg', 'ratio' => '3 / 2', 'caption' => 'Flat'],
        ]);

        $this->assertSame(['One.', 'Two.'], $body[0]['paragraphs']);
        $this->assertSame('Flat heading', $body[1]['text']);
        $this->assertStringEndsWith('posts/flat.jpg', $body[2]['src']);
    }

    public function test_what_the_dashboard_saved_wins_and_the_old_keys_fill_the_gaps(): void
    {
        $body = $this->bodyOf([
            ['type' => 'heading', 'text' => 'Old heading', 'data' => ['text' => 'Edited heading']],
            ['type' => 'quote', 'text' => 'Old quote', 'data' => ['text' => null]],
        ]);

        $this->assertSame('Edited heading', $body[0]['text']);
        $this->assertSame('Old quote', $body[1]['text']);
    }

    public function test_a_figure_with_no_image_yet_has_no_src(): void
    {
        $body = $this->bodyOf([
            ['type' => 'figure', 'data' => ['path' => null, 'ratio' => '3 / 2', 'caption' => 'Not uploaded']],
        ]);

        $this->assertArrayNotHasKey('src', $body[0]);
        $this->assertSame('Not uploaded', $body[0]['caption']);
    }

    public function test_a_post_with_no_body_returns_an_empty_list(): void
    {
        $post = Post::create(['title' => ['en' => 'Empty'], 'is_published' => true, 'published_on' => now()]);

        $this->getJson("/api/posts/{$post->slug}")->assertOk()->assertJsonPath('data.body', []);
    }
}
