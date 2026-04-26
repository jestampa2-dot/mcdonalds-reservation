<?php

namespace Tests\Unit\Support\Timeline;

use App\Models\Reservation;
use App\Models\User;
use App\Support\Timeline\ReservationTimelineBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationTimelineBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_builds_notifications_for_upcoming_active_reservations(): void
    {
        Carbon::setTestNow('2026-04-23 09:00:00');

        $builder = new ReservationTimelineBuilder();
        $reservation = new Reservation([
            'id' => 10,
            'booking_reference' => 'MCD-1001',
            'package_name' => 'Birthday Blast',
            'branch' => 'McDo Uptown',
            'reservation_type' => 'birthday',
            'event_date' => '2026-04-24',
            'event_time' => '10:00:00',
            'duration_hours' => 4,
            'status' => 'confirmed',
        ]);
        $reservation->setRelation('assignedStaff', new User(['name' => 'Crew One']));

        $notifications = $builder->notifications(collect([
            $reservation,
            new Reservation([
                'id' => 11,
                'booking_reference' => 'MCD-1002',
                'event_date' => '2026-04-20',
                'event_time' => '08:00:00',
                'status' => 'completed',
            ]),
        ]));

        $this->assertCount(1, $notifications);
        $this->assertSame('MCD-1001', $notifications[0]['booking_reference']);
        $this->assertSame('Crew One', $notifications[0]['assigned_staff_name']);
        $this->assertSame('Confirmed event coming up soon.', $notifications[0]['message']);
    }

    public function test_it_builds_history_and_cancellations_without_crashing_on_missing_time(): void
    {
        Carbon::setTestNow('2026-04-23 09:00:00');

        $builder = new ReservationTimelineBuilder();
        $completedReservation = new Reservation([
            'id' => 20,
            'booking_reference' => 'MCD-2001',
            'package_name' => 'Business Meeting',
            'branch' => 'McDo Center',
            'reservation_type' => 'business',
            'event_date' => '2026-04-22',
            'event_time' => null,
            'duration_hours' => 3,
            'status' => 'completed',
            'service_status' => 'available',
            'checked_in_by' => 'Manager Mike',
        ]);

        $cancelledReservation = new Reservation([
            'id' => 21,
            'booking_reference' => 'MCD-2002',
            'package_name' => 'Family Gathering',
            'branch' => 'McDo North',
            'reservation_type' => 'birthday',
            'event_date' => '2026-04-21',
            'event_time' => '15:00:00',
            'duration_hours' => 2,
            'status' => 'cancelled',
            'service_status' => 'available',
            'name' => 'Chris Customer',
            'notes' => 'Moved to another branch.',
        ]);

        $history = $builder->history(collect([$completedReservation]));
        $cancelled = $builder->cancelled(collect([$completedReservation, $cancelledReservation]));

        $this->assertCount(1, $history);
        $this->assertSame('Time unavailable', $history[0]['event_time']);
        $this->assertSame('Manager Mike', $history[0]['checked_in_by']);

        $this->assertCount(1, $cancelled);
        $this->assertSame('Chris Customer', $cancelled[0]['customer_name']);
        $this->assertSame('Moved to another branch.', $cancelled[0]['cancelled_note']);
    }
}
