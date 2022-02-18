<?php

namespace Tests\Feature;

use App\Console\Commands\DueReservationNotification;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NewHostReservation;
use App\Notifications\NewHostReservationStart;
use App\Notifications\NewUserReservation;
use App\Notifications\NewUserReservationStart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReservationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itReturnsReservationsThatBelongsToTheUser()
    {
        $user = User::factory()->create();

        $token = $user->createToken('token', ['reservation.show']);

        $reservation = Reservation::factory()->for($user)->create();

        $image = $reservation->office->images()->create(['path' => 'office_image.jpg']);

        $reservation->office()->update([
            'featured_image_id' => $image->id
        ]);

        Reservation::factory()->for($user)->count(3)->create();
        Reservation::factory()->create();

        $response = $this->getJson(
            '/api/reservations?user_id' . http_build_query([
                'user_id' => $user->id
            ]),
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        $response->assertJsonCount(4, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'office'
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
    public function itReturnsReservationsBetweenTwoDates()
    {
        $start_date = '2021-09-04';
        $end_Date = '2021-10-10';

        $user = User::factory()->create();
        $token = $user->createToken('token', ['reservation.show']);

        $reservations = Reservation::factory()->for($user)->createMany(
            [
                [
                    'start_date' => '2021-09-05',
                    'end_date' => '2021-10-01'
                ],
                [
                    'start_date' => '2021-09-10',
                    'end_date' => '2021-10-03'
                ],
                [
                    'start_date' => '2021-10-01',
                    'end_date' => '2021-10-10'
                ],
                [
                    'start_date' => '2021-10-01',
                    'end_date' => '2021-10-10'
                ],
                [
                    'start_date' => '2021-08-01',
                    'end_date' => '2021-11-10'
                ]
            ]
        );


        Reservation::factory()->for($user)->create(
            [
                'start_date' => '2021-05-01',
                'end_date' => '2021-06-10'
            ]
        );

        $response = $this->getJson(
            '/api/reservations?' . http_build_query([
                'from_date' => $start_date,
                'to_date' => $end_Date
            ]),
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertJsonCount(5, 'data');
        $this->assertEquals($reservations->modelKeys(), collect($response->json('data'))->pluck('id')->toArray());
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'office'
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
    public function itFiltersReservationsByStatus()
    {

        $user = User::factory()->create();
        $token = $user->createToken('token', ['reservation.show']);
        Reservation::factory(4)->for($user)->create();
        Reservation::factory(2)->canceled()->for($user)->create();

        $response = $this->getJson(
            '/api/reservations?' . http_build_query([
                'status' => Reservation::STATUS_ACTIVE,
            ]),
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertJsonCount(4, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'office'
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
    public function itFiltersReservationsByOfficeAndStatus()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $token = $user->createToken('token', ['reservation.show']);

        Reservation::factory(3)->canceled()->for($user)->create(
            ['office_id' => $office->id]
        );

        Reservation::factory(2)->for($user)->create();

        $response = $this->getJson(
            '/api/reservations?' . http_build_query([
                'office_id' => $office->id,
                'status' => Reservation::STATUS_CANCELED
            ]),
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.office_id', $office->id);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'office'
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
    public function itFiltersReservationOfLoggedHost()
    {
        $user = User::factory()->create();
        $token = $user->createToken('token', ['reservation.show']);

        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->create(
            ['office_id' => $office->id]
        );

        Reservation::factory(2)->for($user)->create();

        $response = $this->getJson(
            '/api/host/reservations?',
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );


        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.office.user_id', $office->user_id);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'office'
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
    public function itCreatesReservation()
    {

        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10,
        ]);
        $token = $user->createToken('token', ['reservation.create']);
        Notification::fake();

        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(40),
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );
        Notification::assertSentTo($user, NewUserReservation::class);
        $response
            ->assertJsonPath('data.price', 36000)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'office_id',
                    'office'
                ],
            ]);
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCantCreateReservationOnOfficeThatBelongsToUser()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10,
        ]);
        $token = $user->createToken('token', ['reservation.create']);

        $response = $this->postJson(
            '/api/reservations?',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(10),
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['office_id' => 'Can\'t make reservation on your office']);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCantCreateReservationForLessThanTowDays()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10,
        ]);
        $token = $user->createToken('token', ['reservation.create']);

        $response = $this->postJson(
            '/api/reservations?',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDay(),
            ],
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
    public function itCanCreateReservationForTowDays()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10,
        ]);
        $token = $user->createToken('token', ['reservation.create']);

        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(2),
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );


        $response->assertCreated();
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCanTCreateReservationOnConlectedDays()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10,
        ]);
        $token = $user->createToken('token', ['reservation.create']);

        Reservation::factory()->for($office)->create([
            'start_date' => now()->addDay(),
            'end_date' => now()->addDays(4)
        ]);

        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(3),
            ],
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
    public function itCanTCreateReservationOnConlectedDays_1()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $token = $user->createToken('token', ['reservation.create']);

        Reservation::factory()->for($office)->create([
            'start_date' => '2022-01-14',
            'end_date' => '2022-01-16'
        ]);

        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => '2022-01-16',
                'end_date' => '2022-01-17'
            ],
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
    public function itCanTCreateReservationOnConlectedDays_2()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $token = $user->createToken('token', ['reservation.create']);

        Reservation::factory()->for($office)->create([
            'start_date' => '2022-01-19',
            'end_date' => '2022-01-20'
        ]);

        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => '2022-01-16',
                'end_date' => '2022-01-19'
            ],
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
    public function itCanTCreateReservationOnPendingOrHiddenOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create(['hidden' => true]);
        $office1 = Office::factory()->pending()->create();

        $token = $user->createToken('token', ['reservation.create']);


        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(10)
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'office' => 'Can\'t make reservation on bending or hidden office'
        ]);
    }

    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCanSendsNotifications()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $token = $user->createToken('token', ['reservation.create']);
        Notification::fake();

        $response = $this->postJson(
            '/api/reservations',
            [
                'office_id' => $office->id,
                'start_date' => now()->addDay(),
                'end_date' => now()->addDays(10)
            ],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        Notification::assertSentTo($user, NewUserReservation::class);
        Notification::assertSentTo($office->user, NewHostReservation::class);

        $response->assertCreated();
    }


    /**
     * A basic test example.
     *
     * @return void
     * @test
     */
    public function itCanSendsNotificationsForDueReservations()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $reservation = Reservation::factory()->create([
            'start_date' => now()->toDateString(),
        ]);
        Notification::fake();


        $this->artisan(DueReservationNotification::class);
        Notification::assertSentTo($reservation->user, NewUserReservationStart::class);
        Notification::assertSentTo($reservation->office->user, NewHostReservationStart::class);

    }

    /**
     * @return void
     * @test
     */
    function ItCancelReservation()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $reservation = Reservation::factory()->for($office)->for($user)->create();
        $token = $user->createToken('token', ['reservation.cancel']);

        $response = $this->deleteJson(
            '/api/reservations/' . $reservation->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertOk()
            ->assertStatus(200)
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', Reservation::STATUS_CANCELED);
    }

    /**
     * @return void
     * @test
     */
    function ItDoseNotCancelReservationForAnotherUser()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $reservation = Reservation::factory()->for($office)->create();
        $token = $user->createToken('token', ['reservation.cancel']);

        $response = $this->deleteJson(
            '/api/reservations/' . $reservation->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertJsonValidationErrors(['reservation' => 'Can\'t cancel this reservation']);
        $this->assertModelExists($reservation);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
        ]);
    }

    /**
     * @return void
     * @test
     */
    function ItDoseNotCancelReservationWithCanceledStatus()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $reservation = Reservation::factory()->for($office)->for($user)->canceled()->create();
        $token = $user->createToken('token', ['reservation.cancel']);

        $response = $this->deleteJson(
            '/api/reservations/' . $reservation->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertJsonValidationErrors(['reservation' => 'Can\'t cancel this reservation']);
        $this->assertModelExists($reservation);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
        ]);
    }

    /**
     * @return void
     * @test
     */
    function ItDoseNotCancelReservationPassingTheStartingDate()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $reservation = Reservation::factory()->for($office)->for($user)->create([
            'start_date' => now()->addDays(-2),
        ]);
        $token = $user->createToken('token', ['reservation.cancel']);

        $response = $this->deleteJson(
            '/api/reservations/' . $reservation->id,
            [],
            [
                'Authorization' => 'Bearer ' . $token->plainTextToken,
            ]
        );

        $response->assertJsonValidationErrors(['reservation' => 'Can\'t cancel this reservation']);
        $this->assertModelExists($reservation);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
        ]);
    }


}
