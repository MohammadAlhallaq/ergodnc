<?php

namespace App\Http\Pipelines\Filtering;

use Closure;
use Illuminate\Pipeline\Pipeline;

class FilterByLocation extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder->when(
            request('lat') && request('lng'),
            fn($builder) => $builder->NearestTo(request('lat'), request('lng'), fn($builder) => $builder->orderBy('id', 'ASC'))
        );
    }
}
