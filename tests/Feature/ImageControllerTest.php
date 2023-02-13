<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itUploadAnImageAndStoreItUnderTheOffice()
    {
        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::assertExists(
            $response->json('data.path')
        );
    }

    /**
     * @test
     */
    public function itDeleteAnImage()
    {
        Storage::put('/office_image.jpg', 'empy');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.jpg'
        ]);
        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertOk();
        $this->assertModelMissing($image);

        Storage::assertMissing(
            'office_image.jpg'
        );
    }

    /**
     * @test
     */
    public function itCannotDeleteTheOnlyImage()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Cannot delete the only image!']);
    }

    /**
     * @test
     */
    public function itCannotDeleteTheFeaturedImage()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $image = $office->images()->create([
            'path' => 'featured_image.jpg'
        ]);

        $office->update([
            'featured_image_id' => $image->id
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Cannot delete the featured image!']);
    }

    /**
     * @test
     */
    public function itCannotDeleteImageThatBelongsToAnotherResource()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $anotherOffice = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image1.jpg'
        ]);
        $anotherOfficeImage = $anotherOffice->images()->create([
            'path' => 'anotherOfficeImage.jpg'
        ]);
        $anotherOffice->images()->create([
            'path' => 'anotherOfficeImage_2.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$anotherOfficeImage->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Cannot delete this image!']);
    }
}
