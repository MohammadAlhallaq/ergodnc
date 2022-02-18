<?php

namespace App\Http\Pipelines\Filtering;

use Closure;
use App\Models\Office;
use Illuminate\Pipeline\Pipeline;

class ShowOfficesForAuthUser extends Pipeline
{

    public function handle($request, Closure $next)
    {
        $builder = $next($request);

        return $builder
            ->when(request('user_id') && request('user_id') == auth()->id(),
                fn($builder) => $builder->where('approval_status', Office::APPROVAL_APPROVED)->where('hidden', false),
                fn($builder) => $builder
            );
    }
}
