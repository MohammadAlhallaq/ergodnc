<?php

namespace App\Http\Pipelines\Filtering;

use Closure;
use Illuminate\Pipeline\Pipeline;

class FilterByTags extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder->when(request('tags'),
            fn($builder) => $builder->has('tags', '=', count(request('tags')))
                ->wherehas('tags', fn($builder) => $builder->whereKey(request('tags')))
        );
    }
}
