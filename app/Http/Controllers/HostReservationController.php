<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HostReservationController extends Controller
{
    function index(): AnonymousResourceCollection
    {
        abort_unless(auth()->user()->tokenCan('reservation.show'), Response::HTTP_FORBIDDEN);

        validator(request()->all(),
            [
                'status' => Rule::in([Reservation::STATUS_CANCELED, Reservation::STATUS_ACTIVE]),
                'office_id' => ['integer'],
                'user_id' => ['integer'],
                'from_date' => ['date', 'required_with:to_date'],
                'to_date' => ['date', 'required_with:from_date', 'after:from_date']
            ]
        )->validate();

        $reservations = Reservation::query()
            ->whereRelation('office', 'user_id', '=', auth()->id())
            ->when(request('user_id'), fn($builder) => $builder->whereUserId(request('office_id')))
            ->when(request('office_id'), fn($builder) => $builder->whereOfficeId(request('office_id')))
            ->when(request('status'), fn($builder) => $builder->whereStatus(request('status')))
            ->when(request('from_date') && request('to_date'),

                fn($builder) => $builder->BetweenDates(request('from_date'), request('to_date'))
            )
            ->with(['office.featuredImage'])
            ->paginate();

        return ReservationResource::collection($reservations);

    }
}
