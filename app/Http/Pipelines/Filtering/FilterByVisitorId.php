<?php

namespace App\Http\Pipelines\Filtering;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class FilterByVisitorId extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder->when(request('visitor_id'), fn(Builder $builder) => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id')));

    }
}
