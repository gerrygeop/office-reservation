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
        $reservation_1 = Reservation::factory()->for($user)->create([
            'start_date' => '2023-03-01',
            'end_date' => '2023-03-15',
        ]);

        $reservation_2 = Reservation::factory()->for($user)->create([
            'start_date' => '2023-03-25',
            'end_date' => '2023-04-15',
        ]);

        $reservation_3 = Reservation::factory()->for($user)->create([
            'start_date' => '2023-03-25',
            'end_date' => '2023-03-29',
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

        $response
            ->assertJsonCount(3, 'data');

        $this
            ->assertEquals(
                [
                    $reservation_1->id,
                    $reservation_2->id,
                    $reservation_3->id
                ],
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
}
