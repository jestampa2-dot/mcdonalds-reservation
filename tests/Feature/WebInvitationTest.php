<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mail.default', 'array');
    }

    public function test_booking_owner_can_invite_and_cancel_a_guest_from_the_web(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $reservation = $this->reservation($owner);

        $response = $this->actingAs($owner)->postJson(route('reservations.invitations.store', $reservation), [
            'name' => 'Web Guest',
            'phone' => '09181234567',
            'email' => 'webguest@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('email_queued', true)
            ->assertJsonPath('invitation.invitee_name', 'Web Guest');

        $invitation = ReservationInvitation::firstOrFail();

        $this->actingAs($owner)
            ->deleteJson(route('reservations.invitations.destroy', [$reservation, $invitation]))
            ->assertOk();

        $this->assertSame('revoked', $invitation->fresh()->status);
    }

    public function test_web_dashboard_shows_sent_and_received_invitations_and_allows_rsvp(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $invitee = $this->customer('guest@example.com', '09181234567');
        $reservation = $this->reservation($owner);
        $invitation = ReservationInvitation::create([
            'reservation_id' => $reservation->id,
            'inviter_id' => $owner->id,
            'invitee_user_id' => $invitee->id,
            'invitee_name' => $invitee->name,
            'invitee_phone' => '+639181234567',
            'invitee_email' => $invitee->email,
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
        ]);

        $this->actingAs($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('bookings.0.invitations.0.id', $invitation->id)
                ->where('bookings.0.invitations.0.status', 'pending'));

        $this->actingAs($invitee)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('receivedInvitations.0.id', $invitation->id)
                ->where('receivedInvitations.0.inviter_name', $owner->name));

        $this->actingAs($invitee)
            ->postJson(route('invitations.respond', $invitation), ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('invitation.status', 'accepted');

        $this->assertSame('accepted', $invitation->fresh()->status);
    }

    private function customer(string $email, string $phone): User
    {
        return User::factory()->create([
            'role' => 'customer',
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    private function reservation(User $owner): Reservation
    {
        return Reservation::create([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'booking_reference' => 'WEB-INVITE-1001',
            'reservation_type' => 'birthday',
            'package_name' => 'Birthday Party Package',
            'branch' => "McDonald's Surigao City",
            'branch_code' => 'surigao-city',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:00:00',
            'duration_hours' => 4,
            'guests' => 15,
            'total_amount' => 2500,
            'status' => 'confirmed',
            'service_status' => 'available',
        ]);
    }
}
