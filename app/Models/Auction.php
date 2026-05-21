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
    'min_participants',
    'max_participants',
    'started_at',
    'ended_at',
    'status',
    'winner_id',
    'payment_deadline',
];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'payment_deadline' => 'datetime',
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