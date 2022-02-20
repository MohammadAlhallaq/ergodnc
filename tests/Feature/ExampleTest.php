<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficeUpdated;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use JetBrains\PhpStorm\Pure;
use Tests\TestCase;

use function GuzzleHttp\Promise\all;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itListTags()
    {
        $response = $this->get('/api/tags');
        $response->assertStatus(200);
        $this->assertIsObject($response);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itListOffices()
    {
        $this->withoutExceptionHandling();

        Office::factory(2)->create();
        $response = $this->get('/api/offices');
        $response->assertStatus(200);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itListOfficesWithPagination()
    {
        Office::factory(4)->create();
        $response = $this->get('/api/offices');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'title',
                ]
            ],
            'links' => [
                'first'
            ]
        ]);
        $response->assertStatus(200);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itShowsOnlyApprovedOffices()
    {
        Office::factory(3)->pending()->make();
        Office::factory(3)->hidden()->make();
        Office::factory(3)->make();
        $response = $this->get('/api/offices');
        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itFiltersByHostId()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();
        Office::factory()->for($user)->create();
        $response = $this->get('/api/offices?user_id=' . $user->id);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();
        $Office = Office::factory()->create();
        $user = User::factory()->create();
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($Office)->for($user)->create();
        $response = $this->get('/api/offices?visitor_id=' . $user->id);
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itHasAdditionalData()
    {

        $user = User::factory()->create();
        $tags = Tag::factory(3)->create();
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);
        $office->images()->create(['path' => 'image.png']);
        $response = $this->get('/api/offices');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'title',
                    'tags',
                    'images',
                    'user'
                ]
            ],
            'links' => [
                'first'
            ]
        ]);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itHasReservationCount()
    {

        Office::factory(3)->create();
        $Office = Office::factory()->create();
        $user = User::factory()->create();
        Reservation::factory(3)->for(Office::factory())->create();
        Reservation::factory(3)->canceled()->for($Office)->for($user)->create();
        $response = $this->get('/api/offices');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'title',
                    'tags',
                    'images',
                    'user',
                    'reservations_count'
                ]
            ],
            'links' => [
                'first'
            ]
        ]);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itOrderByDistance()
    {
        Office::factory()->create([
            'lat' => 51.93141348061432,
            'lng' => 4.489390995523433,
            'title' => 'crosswijk'
        ]);

        Office::factory()->create([
            'lat' => 51.942413639257424,
            'lng' => 4.538327076745169,
            'title' => 'hat'
        ]);

        Office::factory()->create([
            'lat' => 51.92464209042156,
            'lng' => 4.507495505033163,
            'title' => 'kralingen'
        ]);

        $response = $this->get('/api/offices?lng=51.92476595832152&lat=4.477567790472324');


        $response->assertStatus(200);
        $this->assertEquals('crosswijk', $response->json('data')[0]['title']);
        $this->assertEquals('kralingen', $response->json('data')[1]['title']);
        $this->assertEquals('hat', $response->json('data')[2]['title']);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itShowsPreviousReservations()
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->canceled()->create();
        Reservation::factory()->for($office)->create();

        // Reservation::factory(3)->for($office)->create();
        $response = $this->get('/api/offices/' . $office->id);
        $this->assertCount(1, $response->json());
        $response->assertJsonStructure([
            'data' => [
                'title',
                'tags',
                'images',
                'user',
                'reservations_count'
            ],
        ]);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itShowsOffice()
    {
        $office = Office::factory()->create();
        // Reservation::factory(3)->for($office)->create();
        $response = $this->get('/api/offices/' . $office->id);
        $response->assertJsonStructure([
            'data' => [
                'title',
                'tags',
                'images',
                'user',
                'reservations_count'
            ],
        ]);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCreatesAnOffice()
    {

        $user = User::factory()->createQuietly();
        $tag = Tag::factory()->create();
        $tag1 = Tag::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/api/offices', [
            'title' => 'test',
            'description' => 'this is office',
            'lat' => 51.942413639257424,
            'lng' => 4.538327076745169,
            'address_line1' => 'address',
            'price_per_day' => 10_000,
            'monthly_discount' => 5,
            'tags' => [$tag->id, $tag1->id],
            'user_id' => $user->id
        ]);


        $response->assertStatus(201)->assertJsonStructure([
            'data' => [
                'title',
                'tags',
                'images',
                'user',
            ],
        ]);

        $this->assertDatabaseHas('offices', [
            'title' => 'test',
        ]);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itDosentCreatesIfScopeNotProvided()
    {

        $user = User::factory()->create();

        $token = $user->createToken('token', ['office.create']);

        $tag = Tag::factory()->create();
        $tag1 = Tag::factory()->create();

        $response = $this->postJson(
            '/api/offices',
            [
                'title' => 'test',
                'description' => 'this is office',
                'lat' => 51.942413639257424,
                'lng' => 4.538327076745169,
                'address_line1' => 'address',
                'price_per_day' => 10_000,
                'monthly_discount' => 5,
                'tags' => [$tag->id, $tag1->id],
                'user_id' => $user->id
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertStatus(201)->assertJsonStructure([
            'data' => [
                'title',
                'tags',
                'images',
                'user',
            ],
        ]);

        $this->assertDatabaseHas('offices', [
            'title' => 'test',
        ]);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itDosntUpdatesIfScopeNotProvided()
    {

        $user = User::factory()->create();
        $token = $user->createToken('token', ['office.update']);
        $tag = Tag::factory(2)->create();
        $office = Office::factory()->for($user)->create();
        $response = $this->putJson(
            '/api/offices/' . $office->id,
            [
                'title' => 'updated test',
                // 'description' => 'this is office',
                // 'lat' => 51.942413639257424,
                // 'lng' => 4.538327076745169,
                // 'address_line1' => 'address',
                // 'price_per_day' => 10_000,
                // 'monthly_discount' => 5,
                // 'tags' => [$tag->id, $tag1->id],
                // 'user_id' => $user->id
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        $this->assertDatabaseHas('offices', ['title' => 'updated test']);
        $response->assertStatus(200)->assertJsonStructure([
            'data' => [
                'title',
                'tags',
                'images',
                'user',
            ],
        ]);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itDosentUpdatesIfAuthorized()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();

        $tags = Tag::factory(2)->create();
        $anoherUser = User::factory()->create();
        $token = $user->createToken('token', ['office.update']);
        $office = Office::factory()->for($anoherUser)->create();
        $office->tags()->attach($tags);
        $response = $this->putJson(
            '/api/offices/' . $office->id,
            [
                'title' => 'updated test',
                // 'description' => 'this is office',
                // 'lat' => 51.942413639257424,
                // 'lng' => 4.538327076745169,
                // 'address_line1' => 'address',
                // 'price_per_day' => 10_000,
                // 'monthly_discount' => 5,
                // 'tags' => [$tag1->id, $tag2->id],
                // 'user_id' => $user->id
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        $response->assertStatus(403);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itUpdatesTags()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $token = $user->createToken('token', ['office.update']);
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);
        $response = $this->putJson(
            '/api/offices/' . $office->id,
            [
                'title' => 'updated test',
                // 'description' => 'this is office',
                // 'lat' => 51.942413639257424,
                // 'lng' => 4.538327076745169,
                // 'address_line1' => 'address',
                // 'price_per_day' => 10_000,
                // 'monthly_discount' => 5,
                'tags' => [$tag1->id, $tag2->id],
                // 'user_id' => $user->id
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        $response->assertJsonCount(2, 'data.tags')->assertJsonPath('data.tags.0.id', $tag1->id);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itUpdatesAndChangesStatus()
    {
        $users = User::factory(3)->create(['is_admin' => true]);
        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();
        Notification::fake();

        $token = $user->createToken('token', ['office.update']);
        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tags);
        $response = $this->putJson(
            '/api/offices/' . $office->id,
            [
                // 'title' => 'updated test',
                // 'description' => 'this is office',
                'lat' => 51.942413639257424,
                'lng' => 4.538327076745169,
                // 'address_line1' => 'address',
                // 'price_per_day' => 10_000,
                // 'monthly_discount' => 5,
                // 'tags' => [$tag1->id, $tag2->id],
                // 'user_id' => $user->id
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        Notification::assertSentTo($users, OfficeUpdated::class);
        $this->assertEquals(Office::APPROVAL_PENDING, $response->json('data')['approval_status']);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itDeletesOffice()
    {
        $user = User::factory()->create();
        // $tags = Tag::factory(2)->create();

        $token = $user->createToken('token', ['office.delete']);
        $office = Office::factory()->for($user)->create();
        // $office->tags()->attach($tags);
        // Reservation::factory()->for($office)->create();

        $r = $this->deleteJson(
            '/api/offices/' . $office->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        $this->assertSoftDeleted($office);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCantDeleteOffice()
    {
        $user = User::factory()->create();
        // $tags = Tag::factory(2)->create();

        $token = $user->createToken('token', ['office.delete']);
        $office = Office::factory()->for($user)->create();
        // $office->tags()->attach($tags);
        Reservation::factory()->for($office)->create();

        $r = $this->deleteJson(
            '/api/offices/' . $office->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $r->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertNotSoftDeleted($office);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itShowsAllOffices()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $token = $user->createToken('token');

        Office::factory()->pending()->for($user)->create();
        Office::factory()->hidden()->for($user)->create();
        Office::factory(3)->for($user)->create();

        $response = $this->getJson(
            '/api/offices?user_id=' . $user->id,
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );


        $response
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itUpdatesFeaturedImagesOfAnOffice()
    {

        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $image = $office->images()->create(['path' => 'image.jpg']);

        $response = $this->put(
            'api/offices/' . $office->id,
            ['featured_image_id' => $image->id],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $this->assertEquals($response->json('data')['featured_image_id'], $image->id);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itDoesntUpdatesFeaturedImagesOfaDifferentOffice()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token');
        $office = Office::factory()->for($user)->create();
        $office1 = Office::factory()->for($user)->create();
        $image = $office1->images()->create(['path' => 'image.jpg']);

        $response = $this->putJson(
            'api/offices/' . $office->id,
            ['featured_image_id' => $image->id],
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
    public function itDeletesImagesWithTheOffice()
    {
        Storage::put('images/image.jpg', 'empty');
        Storage::put('images/image1.jpg', 'empty');


        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $image = $office->images()->create(['path' => 'image.jpg']);
        $image1 = $office->images()->create(['path' => 'image1.jpg']);


        $token = $user->createToken('token', ['office.delete']);

        $response = $this->deleteJson(
            '/api/offices/' . $office->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertStatus(200);

        Storage::assertMissing([
            'images/image.jpg',
            'images/image1.jpg'
        ]);

        $this->assertSoftDeleted($office);
        $this->assertModelMissing($image);
        $this->assertModelMissing($image1);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itFilterOfficesByTags()
    {
        $tags = Tag::all();
        Office::factory()->hasAttached($tags)->create();
        Office::factory()->hasAttached($tags)->create();
        Office::factory()->hasAttached($tags->first())->create();
        Office::factory()->create();

        $response = $this->get('/api/offices?' . http_build_query([
                'tags' => $tags->pluck('id')->toArray(),
            ]));

        $response->assertOk()->assertJsonCount(2, 'data');
    }



    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itShowsAllOfficesOrderedByFirstReservation()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token');

        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();
        $office3 = Office::factory()->create();

        Reservation::factory()->for($office1)->create(['created_at' => now()->addDays(-11)->toDateString()]);
        Reservation::factory()->for($office1)->create(['created_at' => now()->addDays(-10)->toDateString()]);
        Reservation::factory()->for($office1)->create(['created_at' => now()->addDays(-9)->toDateString()]);
        Reservation::factory()->for($office1)->create(['created_at' => now()->addDays(-8)->toDateString()]);
        Reservation::factory()->for($office2)->create(['created_at' => now()->addDays(-7)->toDateString()]);
        Reservation::factory()->for($office2)->create(['created_at' => now()->addDays(-6)->toDateString()]);
        Reservation::factory()->for($office2)->create(['created_at' => now()->addDays(-5)->toDateString()]);
        Reservation::factory()->for($office2)->create(['created_at' => now()->addDays(-4)->toDateString()]);
        Reservation::factory()->for($office3)->create(['created_at' => now()->addDays(-3)->toDateString()]);
        Reservation::factory()->for($office3)->create(['created_at' => now()->addDays(-2)->toDateString()]);
        Reservation::factory()->for($office3)->create(['created_at' => now()->addDays(-1)->toDateString()]);
        Reservation::factory()->for($office3)->create(['created_at' => now()->addDays(-8)->toDateString()]);

        $response = $this->getJson(
            '/api/offices?byReservation=true',
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $this->assertEquals($office1->id, $response->json('data')[0]['id']);
        $this->assertEquals($office3->id, $response->json('data')[1]['id']);
        $this->assertEquals($office2->id, $response->json('data')[2]['id']);
    }
}
