<?php

namespace App\Http\Pipelines\Filtering;

use App\Models\Reservation;
use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderByFirstReservation extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder->when(
            request('byReservation') == 'true',
            fn(Builder $builder) => $builder->orderBy(Reservation::select(DB::raw('min(created_at)'))->whereColumn('offices.id', 'reservations.office_id')->groupBy('office_id'))
        );
    }
}
