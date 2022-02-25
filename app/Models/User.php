<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use MohammadAlhallaq\ChangeAndNotify\Contracts\NotifyWhenDirtyContract;
use MohammadAlhallaq\ChangeAndNotify\Traits\NotifyWhenDirtyTrait;

class User extends Authenticatable implements NotifyWhenDirtyContract
{
    use HasApiTokens, HasFactory, Notifiable, NotifyWhenDirtyTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean'
    ];

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    function passwordColumnName(): string
    {
        return 'password';
    }
}
