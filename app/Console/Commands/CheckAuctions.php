<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckAuctions extends Command
{
    protected $signature   = 'auctions:check';
    protected $description = 'Kiểm tra và cập nhật trạng thái phiên đấu giá';

    public function handle()
    {
        // 1. Hủy phiên pending đã hết giờ mà không đủ người
        Auction::where('status', 'pending')
            ->where('ended_at', '<=', now())
            ->update(['status' => 'cancelled']);

        // 2. Kết thúc phiên active đã hết giờ
        $expiredAuctions = Auction::where('status', 'active')
            ->where('ended_at', '<=', now())
            ->get();

        foreach ($expiredAuctions as $auction) {
            DB::transaction(function () use ($auction) {
                $highestBid = Bid::where('auction_id', $auction->id)
                    ->where('type', 'bid')
                    ->orderBy('amount', 'desc')
                    ->first();

                if ($highestBid) {
                    $auction->update([
                        'status'           => 'completed',
                        'winner_id'        => $highestBid->user_id,
                        'payment_deadline' => now()->addDay(),
                    ]);
                } else {
                    $auction->update(['status' => 'cancelled']);
                }
            });
        }

        $this->info('Kiểm tra phiên đấu giá hoàn tất!');
    }
}