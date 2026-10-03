<?php

namespace Tests\Feature;

use App\Filament\Resources\Workshops\Pages\CreateWorkshop;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Creating a workshop failed with a server error whenever the price or the start
 * date was left blank: the form sent null into columns that cannot hold it. A
 * blank price now means free, and a missing date is asked for on the field.
 */
class WorkshopCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_a_complete_workshop_is_created(): void
    {
        Livewire::test(CreateWorkshop::class)
            ->fillForm([
                'title_en' => 'Landscape masterclass',
                'mode_en' => 'In person',
                'level_en' => 'Beginner',
                'location_en' => 'Erbil',
                'starts_on' => '2026-11-10',
                'ends_on' => '2026-11-12',
                'price_minor' => 48000,
                'seats_total' => 12,
                'is_published' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workshop = Workshop::query()->sole();
        $this->assertSame(48000, $workshop->price_minor);
        $this->assertSame('landscape-masterclass', $workshop->slug);
    }

    public function test_a_blank_price_makes_the_workshop_free_instead_of_failing(): void
    {
        Livewire::test(CreateWorkshop::class)
            ->fillForm(['title_en' => 'Free walk', 'starts_on' => '2026-12-01', 'price_minor' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Workshop::query()->sole()->price_minor);
    }

    public function test_a_missing_start_date_is_asked_for_instead_of_failing(): void
    {
        Livewire::test(CreateWorkshop::class)
            ->fillForm(['title_en' => 'Undated', 'starts_on' => null])
            ->call('create')
            ->assertHasFormErrors(['starts_on' => 'required']);

        $this->assertSame(0, Workshop::count());
    }

    public function test_a_new_workshop_starts_with_empty_lists_and_blank_rows_are_dropped(): void
    {
        Livewire::test(CreateWorkshop::class)
            ->assertSet('data.outcomes', [])
            ->assertSet('data.syllabus', [])
            ->assertSet('data.faqs', [])
            ->fillForm([
                'title_en' => 'Lists',
                'starts_on' => '2026-12-01',
                'outcomes' => [['value' => 'Reading the light'], ['value' => null]],
                'faqs' => [['q' => null, 'a' => null], ['q' => 'Do I need a tripod?', 'a' => 'Yes.']],
                'syllabus' => [['day' => 'Day 01', 'title' => 'Arrival', 'slots' => [['time' => null, 'what' => null]]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workshop = Workshop::query()->sole();
        $this->assertSame(['Reading the light'], $workshop->outcomes);
        $this->assertSame([['q' => 'Do I need a tripod?', 'a' => 'Yes.']], $workshop->faqs);
        $this->assertSame([], $workshop->syllabus[0]['slots']);
        $this->assertSame([], $workshop->included);
    }

    public function test_cleared_seats_and_currency_are_asked_for_instead_of_failing(): void
    {
        Livewire::test(CreateWorkshop::class)
            ->fillForm(['title_en' => 'No seats', 'starts_on' => '2026-12-01', 'seats_total' => null, 'currency' => null])
            ->call('create')
            ->assertHasFormErrors(['seats_total' => 'required', 'currency' => 'required']);

        $this->assertSame(0, Workshop::count());
    }
}
