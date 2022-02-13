<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_CANCELED = 2;

    protected $casts = [
        'price' => 'integer',
        'status' => 'integer',
        'start_date' => 'immutable_date',
        'end_date' => 'immutable_date',
        'wifi_password' => 'encrypted'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        $query->where(function ($query) use ($from, $to) {
            $query
                ->whereBetween('start_date', [$from, $to])
                ->orWhereBetween('end_date', [$from, $to])
                ->orWhere(function ($query) use ($from, $to) {
                    $query
                        ->where([['start_date', '<', $from], ['end_date', '>', $to]]);
                });
        });
    }

    public function scopeActiveBetweenDates($query, $start, $end)
    {
        $query->whereStatus(Reservation::STATUS_ACTIVE)->betweenDates($start, $end);
    }

}
