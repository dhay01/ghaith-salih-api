<?php

namespace Tests\Feature;

use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Resources\Reservations\Pages\EditReservation;
use App\Mail\ReservationReceived;
use App\Mail\ReservationSubmitted;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's Create page used to write the row itself, which skipped the
 * workshop entirely and failed on a NOT NULL constraint. These tests pin the
 * behaviour it should have had.
 */
class ManualReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function workshop(int $seats = 10): Workshop
    {
        return Workshop::create([
            'title' => ['en' => 'The Language of Light'],
            'mode' => ['en' => '2-day studio intensive'],
            'level' => ['en' => 'Intermediate'],
            'location' => ['en' => 'Baghdad'],
            'price_minor' => 48000,
            'seats_total' => $seats,
            'starts_on' => now()->addMonth()->toDateString(),
            'is_published' => true,
        ]);
    }

    protected function actingAsAdmin(): self
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        return $this;
    }

    /** @param array<string, mixed> $overrides */
    protected function fill(Workshop $workshop, array $overrides = []): array
    {
        return array_merge([
            'workshop_id' => $workshop->id,
            'name' => 'Zainab Hassan',
            'phone' => '+964 770 000 0000',
            'email' => 'zainab@example.test',
            'age' => 31,
            'gender' => 'female',
            'seats' => 2,
            'send_confirmation' => false,
        ], $overrides);
    }

    public function test_a_manual_reservation_is_created_with_its_workshop(): void
    {
        Mail::fake();
        $workshop = $this->workshop();

        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->fill($workshop))
            ->call('create')
            ->assertHasNoFormErrors();

        $reservation = Reservation::sole();

        $this->assertSame($workshop->id, $reservation->workshop_id);
        $this->assertSame('Zainab Hassan', $reservation->name);
        $this->assertSame(31, (int) $reservation->age);
        $this->assertSame(2, $reservation->seats);
        $this->assertSame(Reservation::STATUS_PENDING, $reservation->status);
        // Stamped so a later change to the question set cannot reinterpret it.
        $this->assertNotNull($reservation->question_set_version);
    }

    public function test_seats_are_checked_so_a_manual_booking_cannot_oversell(): void
    {
        Mail::fake();
        $workshop = $this->workshop(seats: 2);

        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->fill($workshop, ['seats' => 3]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(Reservation::STATUS_WAITLISTED, Reservation::sole()->status);
    }

    public function test_the_applicant_is_not_emailed_unless_staff_ask(): void
    {
        Mail::fake();
        $workshop = $this->workshop();

        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->fill($workshop))
            ->call('create')
            ->assertHasNoFormErrors();

        Mail::assertNotQueued(ReservationSubmitted::class);
        // The seat count changed either way, so the studio is still told.
        Mail::assertQueued(ReservationReceived::class);
    }

    public function test_the_applicant_is_emailed_when_staff_opt_in(): void
    {
        Mail::fake();
        $workshop = $this->workshop();

        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->fill($workshop, ['send_confirmation' => true]))
            ->call('create')
            ->assertHasNoFormErrors();

        Mail::assertQueued(ReservationSubmitted::class);
    }

    public function test_applicant_fields_are_typeable_when_creating(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->assertFormFieldIsEnabled('name')
            ->assertFormFieldIsEnabled('phone')
            ->assertFormFieldIsEnabled('email')
            ->assertFormFieldIsEnabled('age')
            ->assertFormFieldIsEnabled('seats')
            ->assertFormFieldIsEnabled('workshop_id');
    }

    public function test_applicant_fields_stay_read_only_when_editing(): void
    {
        Mail::fake();
        $workshop = $this->workshop();

        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->fill($workshop))
            ->call('create');

        // What the applicant submitted is a record, not something staff retype.
        Livewire::test(EditReservation::class, ['record' => Reservation::sole()->getKey()])
            ->assertFormFieldIsDisabled('name')
            ->assertFormFieldIsDisabled('phone')
            ->assertFormFieldIsDisabled('workshop_id')
            ->assertFormFieldIsEnabled('status');
    }

    public function test_a_workshop_is_required(): void
    {
        Mail::fake();
        $workshop = $this->workshop();

        $this->actingAsAdmin();

        Livewire::test(CreateReservation::class)
            ->fillForm($this->fill($workshop, ['workshop_id' => null]))
            ->call('create')
            ->assertHasFormErrors(['workshop_id']);

        $this->assertSame(0, Reservation::count());
    }
}
