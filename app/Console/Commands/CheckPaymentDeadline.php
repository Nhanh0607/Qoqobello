<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class CheckPaymentDeadline extends Command
{
    protected $signature   = 'auctions:check-payment';
    protected $description = 'Hủy các đơn hàng quá hạn thanh toán';

    public function handle()
    {
        // Tìm các phiên đã completed, chưa thanh toán, quá hạn
        $expiredPayments = Auction::where('status', 'completed')
            ->where('is_paid', false)
            ->where('payment_deadline', '<=', now())
            ->get();

        foreach ($expiredPayments as $auction) {
            $auction->update([
                'winner_id'        => null,
                'payment_deadline' => null,
                'is_paid'          => false,
                'status'           => 'cancelled',
            ]);

            $this->info('Đã hủy phiên #' . $auction->id . ' vì quá hạn thanh toán');
        }

        $this->info('Kiểm tra hạn thanh toán hoàn tất!');
    }
}