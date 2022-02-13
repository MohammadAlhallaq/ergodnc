<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficePolicy
{
    use HandlesAuthorization;

    public function update(User $user, Office $office){
        return $office->user()->is($user);
    }


    public function delete(User $user, Office $office){
        return $office->user()->is($user);
    }
}
