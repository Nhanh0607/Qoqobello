<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Auction extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'start_price',
        'current_price',
        'bid_increment',
        'unlock_cost',
        'min_participants',
        'max_participants',
        'started_at',
        'ended_at',
        'status',
        'winner_id',
        'payment_deadline',
        'is_paid',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'payment_deadline' => 'datetime',
        'is_paid'          => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function participants()
    {
        return $this->hasMany(AuctionParticipant::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}