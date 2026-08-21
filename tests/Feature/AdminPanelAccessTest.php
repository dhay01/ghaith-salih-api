<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/admin` is the only authenticated surface in this app and it lists every
 * applicant's name, phone and email. These tests exist so that access stays opt-in:
 * a plain `users` row must never be enough to read the reservations dashboard.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_a_user_without_the_admin_flag_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_a_user_with_the_admin_flag_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_a_user_without_the_admin_flag_cannot_read_reservations(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/reservations')
            ->assertForbidden();
    }

    public function test_the_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.test',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
    }
}
