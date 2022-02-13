<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCreatesImageForOffice()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $image = UploadedFile::fake()->image('cover.jpg');

        $response = $this->post(
            'api/offices/' . $office->id . '/images',
            compact('image'),
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        Storage::assertExists([
            $office->refresh()->images->first()->path
        ]);

    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itdeletesImage()
    {

        Storage::put('images/image.jpg', 'empty');

        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $image = $office->images()->create(['path' => 'image.jpg']);
        $office->images()->create(['path' => 'image1.jpg']);

        $response = $this->deleteJson(
            'api/offices/' . $office->id . '/images/ ' . $image->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertStatus(200);
        $this->assertModelMissing($image);
        Storage::assertMissing([
            '/images/' . $image->path
        ]);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCantdeletesTheOnlyImage()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $image = $office->images()->create(['path' => 'image.jpg']);

        $response = $this->deleteJson(
            'api/offices/' . $office->id . '/images/ ' . $image->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertUnprocessable();
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCantdeletesTheFeaturedImage()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $image = $office->images()->create(['path' => 'image.jpg']);
        $office->images()->create(['path' => 'image1.jpg']);
        $office->update([
            'featured_image_id' => $image->id
        ]);

        $response = $this->deleteJson(
            'api/offices/' . $office->id . '/images/ ' . $image->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertUnprocessable();
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCantdeletesImageForDifferentresource()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $office1 = Office::factory()->for($user)->create();
        $image1 = $office1->images()->create(['path' => 'image1.jpg']);

        $response = $this->deleteJson(
            'api/offices/' . $office->id . '/images/ ' . $image1->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        $response->assertNotFound();
    }

}
