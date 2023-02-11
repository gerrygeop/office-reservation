<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListAllOfficesInPaginatedWay()
    {
        Office::factory(30)->create();

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title']
                ]
            ]);
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

        $response->assertOk();
    }

    /**
     * @test
     */
    public function itFiltersByUsertId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $response = $this->get('/api/offices?user_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();

        $visitor = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($visitor)->create();

        $response = $this->get('/api/offices?visitor_id=' . $visitor->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
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

        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    /**
     * @test
     */
    public function itOrderByDistanceWhenCoordinatesAreProvided()
    {
        Office::factory()->create([
            'lat' => '-1.246683793171039',
            'lng' => '116.85410448618018',
            'title' => 'Balikpapan'
        ]);

        Office::factory()->create([
            'lat' => '-3.3167067084798783',
            'lng' => '114.58837533512606',
            'title' => 'Banjarmasin'
        ]);

        Office::factory()->create([
            'lat' => '-0.43251811182673117',
            'lng' => '116.98703320222951',
            'title' => 'Tenggarong'
        ]);

        $response = $this->get('/api/offices?lat=-0.4917968112716624&lng=117.14377147229592');
        $response->assertOk();

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

        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    /**
     * @test
     */
    public function itCreateAnOffice()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->createQuietly();
        $tags = Tag::factory(2)->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', Office::factory()->raw([
            'tags' => $tags->pluck('id')->toArray(),
        ]));

        // $response = $this->postJson('/api/offices', [
        //     'title' => 'Kansas',
        //     'description' => 'description',
        //     'lat' => '-1.246683793171039',
        //     'lng' => '116.85410448618018',
        //     'address_line1' => 'address 1',
        //     'price_per_day' => 10_000,
        //     'monthly_discount' => 5,
        //     'tags' => [$tag1->id, $tag2->id],
        // ]);

        $response->assertCreated()
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2, 'data.tags');

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     */
    public function itDoesntAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, []);

        $response = $this->postJson('/api/offices');

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function itDoesntAllowCreatingIfScopeIsProvided()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['office.create']);

        $response = $this->postJson('/api/offices');

        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->status());
    }

    /**
     * @test
     */
    public function itUpdateAnOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(3)->create();
        $anotherTag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'title' => 'Wana Group',
            'tags' => [$tags[0]->id, $anotherTag->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonPath('data.title', 'Wana Group');
    }

    /**
     * @test
     */
    public function itDoesntUpdateOfficeThatDoesntBelongsToUser()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $office = Office::factory()->for($anotherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'title' => 'Wana Group'
        ]);

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office->approval_status = Office::APPROVAL_APPROVED;
        $office->save();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'lat' => '-0.43251811182673117',
            'lng' => '116.98703320222951',
        ]);

        $response->assertOk();

        Notification::assertSentTo($admin, OfficePendingApproval::class);

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING,
        ]);
    }

    /**
     * @test
     */
    public function itCanDeleteOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertOk();
        $this->assertSoftDeleted($office);
    }

    /**
     * @test
     */
    public function itCannotDeleteAnOfficeThatHasReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'deleted_at' => null,
        ]);
    }
}
