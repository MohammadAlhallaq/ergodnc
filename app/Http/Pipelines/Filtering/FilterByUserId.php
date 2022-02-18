<?php

namespace App\Http\Pipelines\Filtering;

use Closure;
use Illuminate\Pipeline\Pipeline;

class FilterByUserId extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder->when(request('user_id'), fn($builder) => $builder->whereUserId(request('user_id')));

    }
}
