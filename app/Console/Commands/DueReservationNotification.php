<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Notifications\NewHostReservationStart;
use App\Notifications\NewUserReservationStart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class DueReservationNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'argodnc:notification-reservation-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Reservation::query()
            ->with('office.user')
            ->where('status', Reservation::STATUS_ACTIVE)
            ->where('start_date', now()->toDateString())
            ->each(function ($reservation) {
                Notification::send($reservation->user, new NewUserReservationStart());
                Notification::send($reservation->office->user, new NewHostReservationStart());
            });


    }
}
