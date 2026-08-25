<?php

namespace Tests\Feature;

use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deleting matters for more than tidying up test rows: because seats are derived
 * from live reservations rather than a stored counter, a deletion has to return
 * those seats to the workshop with no reconciliation step.
 */
class ReservationDeletionTest extends TestCase
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

    protected function reservation(Workshop $workshop, int $seats = 2, string $name = 'Zainab'): Reservation
    {
        return $workshop->reservations()->create([
            'name' => $name,
            'phone' => '+964 770 000 0000',
            'seats' => $seats,
            'answers' => [],
            'question_set_version' => 'v1',
            'status' => Reservation::STATUS_PENDING,
            'locale' => 'en',
        ]);
    }

    public function test_a_reservation_can_be_deleted_from_the_table(): void
    {
        $workshop = $this->workshop();
        $reservation = $this->reservation($workshop);

        $this->assertSame(8, $workshop->seatsLeft());

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListReservations::class)
            ->callTableAction('delete', $reservation);

        $this->assertSame(0, Reservation::count());
        $this->assertSame(10, $workshop->fresh()->seatsLeft());
    }

    public function test_reservations_can_be_deleted_in_bulk(): void
    {
        $workshop = $this->workshop();
        $a = $this->reservation($workshop, 2, 'Zainab');
        $b = $this->reservation($workshop, 3, 'Omar');

        $this->assertSame(5, $workshop->seatsLeft());

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListReservations::class)
            ->callTableBulkAction('delete', [$a, $b]);

        $this->assertSame(0, Reservation::count());
        $this->assertSame(10, $workshop->fresh()->seatsLeft());
    }

    public function test_deleting_a_booking_lets_a_waitlisted_seat_count_recover(): void
    {
        $workshop = $this->workshop(seats: 4);
        $big = $this->reservation($workshop, 4, 'Fills the room');

        $this->assertSame(0, $workshop->seatsLeft());
        $this->assertTrue($workshop->isFull());

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListReservations::class)
            ->callTableAction('delete', $big);

        $this->assertFalse($workshop->fresh()->isFull());
        $this->assertSame(4, $workshop->fresh()->seatsLeft());
    }
}
