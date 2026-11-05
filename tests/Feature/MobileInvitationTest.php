<?php

namespace Tests\Feature;

use App\Jobs\SendInvitationEmail;
use App\Mail\ReservationInvitationMail;
use App\Models\Reservation;
use App\Models\ReservationInvitation;
use App\Models\User;
use App\Services\InvitationEmailGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mail.default', 'array');
    }

    public function test_booking_owner_can_invite_a_registered_customer_by_email_and_in_app(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $invitee = $this->customer('guest@example.com', '09181234567', true);
        $reservation = $this->reservation($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", [
            'name' => 'Invited Guest',
            'phone' => '+63 918 123 4567',
            'email' => strtoupper($invitee->email),
        ])->assertCreated()
            ->assertJsonPath('email_queued', true)
            ->assertJsonPath('invitation.status', 'pending')
            ->assertJsonPath('invitation.invitee_phone', '+639181234567');

        $this->assertDatabaseHas('reservation_invitations', [
            'reservation_id' => $reservation->id,
            'invitee_user_id' => $invitee->id,
            'invitee_phone' => '+639181234567',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('sms_jobs', 1);

        Sanctum::actingAs($invitee);
        $this->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('receivedInvitations.0.inviter_name', $owner->name)
            ->assertJsonPath('receivedInvitations.0.booking_reference', $reservation->booking_reference);
    }

    public function test_invitee_can_accept_and_owner_sees_the_rsvp_status(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $invitee = $this->customer('guest@example.com', '09181234567');
        $reservation = $this->reservation($owner);
        $invitation = $this->invitation($reservation, $owner, $invitee);

        Sanctum::actingAs($invitee);
        $this->postJson("/api/mobile/invitations/{$invitation->id}/respond", [
            'status' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('invitation.status', 'accepted');

        $this->assertDatabaseHas('reservation_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
        $this->assertNotNull($invitation->fresh()->responded_at);

        Sanctum::actingAs($owner);
        $this->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('bookings.0.invitations.0.status', 'accepted');
    }

    public function test_an_unrelated_customer_cannot_answer_an_invitation(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $invitee = $this->customer('guest@example.com', '09181234567');
        $unrelated = $this->customer('other@example.com', '09191234567');
        $invitation = $this->invitation($this->reservation($owner), $owner, $invitee);

        Sanctum::actingAs($unrelated);
        $this->postJson("/api/mobile/invitations/{$invitation->id}/respond", [
            'status' => 'accepted',
        ])->assertForbidden();

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_self_duplicate_and_inactive_booking_invitations_are_rejected(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $reservation = $this->reservation($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", [
            'name' => $owner->name,
            'phone' => $owner->phone,
            'email' => $owner->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $payload = ['name' => 'Guest', 'phone' => '09181234567', 'email' => 'guest@example.com'];
        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", $payload)->assertCreated();
        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $reservation->update(['status' => 'cancelled']);
        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", [
            'name' => 'Another Guest',
            'phone' => '09191234567',
        ])->assertUnprocessable();
    }

    public function test_invitation_worker_emails_a_pending_invitation_and_skips_a_declined_invitation(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $reservation = $this->reservation($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", [
            'name' => 'Guest',
            'phone' => '09181234567',
            'email' => 'guest@example.com',
        ])->assertCreated();

        $invitation = ReservationInvitation::firstOrFail();
        $this->assertStringContainsString(
            'View invitation and RSVP',
            (new ReservationInvitationMail($invitation->load(['reservation', 'inviter'])))->render(),
        );
        Mail::fake();
        (new SendInvitationEmail($invitation->id))->handle(app(InvitationEmailGateway::class));

        Mail::assertSent(ReservationInvitationMail::class, fn (ReservationInvitationMail $mail) =>
            $mail->hasTo('guest@example.com') && $mail->invitation->is($invitation));

        $invitation->update(['status' => 'declined', 'responded_at' => now()]);
        (new SendInvitationEmail($invitation->id))->handle(app(InvitationEmailGateway::class));

        Mail::assertSent(ReservationInvitationMail::class, 1);
    }

    public function test_broken_email_queue_does_not_prevent_an_in_app_invitation(): void
    {
        $owner = $this->customer('owner@example.com', '09171234567');
        $reservation = $this->reservation($owner);
        Schema::drop('sms_jobs');
        Sanctum::actingAs($owner);

        $this->postJson("/api/mobile/reservations/{$reservation->id}/invitations", [
            'name' => 'Guest',
            'phone' => '09181234567',
            'email' => 'guest@example.com',
        ])->assertCreated()
            ->assertJsonPath('email_queued', false)
            ->assertJsonPath('invitation.status', 'pending');

        $this->assertDatabaseHas('reservation_invitations', [
            'reservation_id' => $reservation->id,
            'invitee_phone' => '+639181234567',
        ]);
    }

    private function customer(string $email, string $phone, bool $smsEnabled = false): User
    {
        return User::factory()->create([
            'role' => 'customer',
            'email' => $email,
            'phone' => $phone,
            'sms_notifications_enabled' => $smsEnabled,
        ]);
    }

    private function reservation(User $owner): Reservation
    {
        return Reservation::create([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'booking_reference' => 'INVITE-1001',
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

    private function invitation(Reservation $reservation, User $owner, User $invitee): ReservationInvitation
    {
        return ReservationInvitation::create([
            'reservation_id' => $reservation->id,
            'inviter_id' => $owner->id,
            'invitee_user_id' => $invitee->id,
            'invitee_name' => $invitee->name,
            'invitee_phone' => '+639181234567',
            'invitee_email' => $invitee->email,
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'pending',
        ]);
    }
}
