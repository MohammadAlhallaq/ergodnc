<?php

namespace App\Http\Pipelines\Filtering;

use App\Models\Reservation;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class ReturnWithEagerLoadedData extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder->with(['tags', 'images', 'user'])
            ->withCount(['reservations' => fn(Builder $builder) => $builder->where('status', Reservation::STATUS_ACTIVE)]);
    }
}
