<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @test
     */
    public function itListsReservationsThatBelongsToTheUser()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create();
        $image = $reservation->office->images()->create([
            'path' => 'featured_image.jpg'
        ]);

        $reservation->office()->update([
            'featured_image_id' => $image->id
        ]);

        Reservation::factory(2)->for($user)->create();
        Reservation::factory(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

    /**
     * @test
     */
    public function itListsReservationFilteredByDateRange()
    {
        $user = User::factory()->create();

        $fromDate = '2023-03-03';
        $toDate = '2023-04-04';

        // Within the date range
        $reservation = Reservation::factory()->for($user)->createMany([
            [
                'start_date' => '2023-03-01',
                'end_date' => '2023-03-15',
            ],
            [
                'start_date' => '2023-03-25',
                'end_date' => '2023-04-15',
            ],
            [
                'start_date' => '2023-03-25',
                'end_date' => '2023-03-29',
            ],
            [
                'start_date' => '2023-03-01',
                'end_date' => '2023-04-15',
            ],
        ]);

        // Within the range but belongs to a different user
        Reservation::factory()->create([
            'start_date' => '2023-03-25',
            'end_date' => '2023-03-29',
        ]);

        // Outside the date range
        Reservation::factory()->for($user)->create([
            'start_date' => '2023-02-25',
            'end_date' => '2023-03-01',
        ]);
        Reservation::factory()->for($user)->create([
            'start_date' => '2023-05-01',
            'end_date' => '2023-05-01',
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?' . http_build_query([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]));

        $response->assertJsonCount(4, 'data');

        $this->assertEquals(
            $reservation->pluck('id')->toArray(),
            collect(
                $response->json('data')
            )->pluck('id')->toArray()
        );
    }

    /**
     * @test
     */
    public function itFiltersResultsByStatus()
    {
        $user = User::factory()->create();

        $reservationActive = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE,
        ]);

        Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_CANCEL,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?' . http_build_query([
            'status' => Reservation::STATUS_ACTIVE,
        ]));

        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservationActive->id);
    }

    /**
     * @test
     */
    public function itFiltersResultsByOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($office)->for($user)->create();

        Reservation::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?' . http_build_query([
            'office_id' => $office->id,
        ]));

        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }

    /**
     * @test
     */
    public function itMakesReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(41),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.price', 36000)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.status', Reservation::STATUS_ACTIVE);
    }
}
