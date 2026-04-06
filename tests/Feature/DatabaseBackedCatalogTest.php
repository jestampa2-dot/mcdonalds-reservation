<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackedCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_booking_catalog_defaults_are_bootstrapped_into_the_database(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $this->assertDatabaseHas('event_types', [
            'code' => 'birthday',
            'label' => 'Birthday Bash',
        ]);

        $this->assertDatabaseHas('booking_packages', [
            'code' => 'playplace-blast',
            'name' => 'The Ultimate Birthday PlayPlace Bash',
        ]);

        $this->assertDatabaseHas('menu_bundles', [
            'code' => 'burger-10',
            'name' => '10 Cheeseburger Meals',
        ]);

        $this->assertDatabaseHas('add_ons', [
            'code' => 'party-host',
            'name' => 'Dedicated Party Host',
        ]);

        $this->assertDatabaseHas('branches', [
            'code' => 'mnl-bgc',
            'name' => "McDonald's BGC High Street",
        ]);

        $this->assertDatabaseHas('booking_settings', [
            'opening_hour' => 7,
            'closing_hour' => 23,
            'default_duration_hours' => 4,
        ]);

        $this->assertDatabaseHas('room_options', [
            'code' => 'birthday-party-room',
            'label' => 'Birthday Party Room',
        ]);

        $this->assertDatabaseHas('pricing_settings', [
            'extension_hourly_rate' => 450,
        ]);
    }
}
