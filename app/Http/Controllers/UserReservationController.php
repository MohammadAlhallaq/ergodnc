<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Notifications\NewHostReservation;
use App\Notifications\NewUserReservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->tokenCan('reservation.show'), Response::HTTP_FORBIDDEN);

        $data = validator(
            request()->all(),
            [
                'status' => Rule::in([Reservation::STATUS_CANCELED, Reservation::STATUS_ACTIVE]),
                'office_id' => ['integer'],
                'from_date' => ['date', 'required_with:to_date'],
                'to_date' => ['date', 'required_with:from_date', 'after:from_date']
            ]
        )->validate();

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(request('office_id'), fn($builder) => $builder->whereOfficeId($data['office_id']))
            ->when(request('status'), fn($builder) => $builder->whereStatus($data['status']))
            ->when(
                request('from_date') && request('to_date'),
                fn($builder) => $builder->BetweenDates($data['from_date'], $data['to_date'])
            )
            ->with(['office.featuredImage'])
            ->paginate();

        return ReservationResource::collection($reservations);
    }

    public function create()
    {
        abort_unless(auth()->user()->tokenCan('reservation.create'), Response::HTTP_FORBIDDEN);

        $data = validator(
            request()->all(),
            [
                'office_id' => ['required', 'integer', Rule::exists('offices', 'id')],
                'start_date' => ['required', 'date:Y-m-d', 'after:today'],
                'end_date' => ['required', 'date:Y-m-d', 'after:start_date'],
            ]
        )->validate();

        try {
            $office = Office::findorfail($data['office_id']);
        } catch (ModelNotFoundException $e) {
            throw ValidationException::withMessages([
                'office_id' => 'Invalid office_id'
            ]);
        }

        if ($office->user_id == auth()->id()) {
            throw ValidationException::withMessages([
                'office_id' => 'Can\'t make reservation on your office'
            ]);
        }

        if ($office->approval_status == Office::APPROVAL_PENDING || $office->hidden) {
            throw ValidationException::withMessages([
                'office' => 'Can\'t make reservation on bending or hidden office'
            ]);
        }


        $reservation = Cache::lock('reservation_office_' . $office->id)->block(4, function () use ($office, $data) {

            $numberOfDays =
                Carbon::parse($data['end_date'])->endOfDay()->diffInDays(
                    Carbon::parse($data['start_date'])->startOfDay()
                ) + 1;


            if ($office->reservations()->ActiveBetweenDates($data['start_date'], $data['end_date'])->exists()) {
                throw ValidationException::withMessages([
                    'office_id' => 'Can\'t make reservation right now'
                ]);
            }

            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28) {
                $price = $price - ($price * $office->monthly_discount / 100);
            }

            return Reservation::create([
                'user_id' => auth()->id(),
                'office_id' => $office->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => Reservation::STATUS_CANCELED,
                'price' => $price,
                'wifi_password' => Str::random(),
            ]);
        });

        Notification::send(auth()->user(), new NewUserReservation($reservation));
        Notification::send($office->user, new NewHostReservation($reservation));

        return ReservationResource::make($reservation->load('office'));
    }

    public function cancel(Reservation $reservation)
    {
        abort_unless(auth()->user()->tokenCan('reservation.cancel'), Response::HTTP_FORBIDDEN);

        throw_if(
            $reservation->user_id == auth()->id()
            ||
            $reservation->status == Reservation::STATUS_CANCELED
            ||
            $reservation->start_date < now()->toTimeString(),
            ValidationException::withMessages(['reservation' => 'Can\'t cancel this reservation'])
        );

        $reservation->update(['status' => Reservation::STATUS_ACTIVE]);

        return ReservationResource::make($reservation->load('office'));
    }
}
