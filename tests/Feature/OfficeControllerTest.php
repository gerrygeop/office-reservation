<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use function PHPUnit\Framework\assertNotNull;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListAllOfficesInPaginatedWay()
    {
        Office::factory(3)->create();

        $response = $this->get('/api/offices');

        // $response->dump();
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function itOnlyListOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();
        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        // $response->dump();
        $response->assertOk();
    }

    /**
     * @test
     */
    public function itFiltersByHostId()
    {
        Office::factory(3)->create();

        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get('/api/offices?host_id=' . $host->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
        // $response->dump();
    }

    /**
     * @test
     */
    public function itFiltersByUserId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get('/api/offices?user_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
        // $response->dump();
    }

    /**
     * @test
     */
    public function itIncludeImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices');
        $response->assertOk();

        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }

    /**
     * @test
     */
    public function itReturnsTheNumberOfActiveReservations()
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCEL]);

        $response = $this->get('/api/offices');
        $response->assertOk();
        // $response->dump();

        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    /**
     * @test
     */
    public function itOrderByDistanceWhenCoordinatesAreProvided()
    {
        $office1 = Office::factory()->create([
            'lat' => '-1.246683793171039',
            'lng' => '116.85410448618018',
            'title' => 'Balikpapan'
        ]);

        $office2 = Office::factory()->create([
            'lat' => '-3.3167067084798783',
            'lng' => '114.58837533512606',
            'title' => 'Banjarmasin'
        ]);

        $office3 = Office::factory()->create([
            'lat' => '-0.43251811182673117',
            'lng' => '116.98703320222951',
            'title' => 'Tenggarong'
        ]);

        $response = $this->get('/api/offices?lat=-0.4917968112716624&lng=117.14377147229592');
        $response->assertOk();
        // $response->dump();

        $this->assertEquals('Tenggarong', $response->json('data')[0]['title']);
        $this->assertEquals('Balikpapan', $response->json('data')[1]['title']);
        $this->assertEquals('Banjarmasin', $response->json('data')[2]['title']);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertEquals('Balikpapan', $response->json('data')[0]['title']);
        $this->assertEquals('Banjarmasin', $response->json('data')[1]['title']);
        $this->assertEquals('Tenggarong', $response->json('data')[2]['title']);
    }

    /**
     * @test
     */
    public function itShowTheOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCEL]);

        $tag = Tag::factory()->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices/' . $office->id);
        $response->assertOk();
        $response->dump();

        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }
}
