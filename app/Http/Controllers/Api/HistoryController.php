<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuctionParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = 10;

        $participants = AuctionParticipant::where('user_id', auth()->id())
            ->with([
                'auction.product',
                'auction.bids' => function ($q) {
                    $q->where('user_id', auth()->id());
                },
            ])
            ->latest()
            ->paginate($perPage);

        $data = $participants->map(function ($participant) {
            $auction = $participant->auction;

            // Kiểm tra auction và product tồn tại
            if (!$auction || !$auction->product) {
                return null;
            }

            $product  = $auction->product;
            $bidCount = $auction->bids->count();
            $status   = $this->getStatus($auction);

            return [
                'auction_id'    => $auction->id,
                'product'       => [
                    'name'  => $product->title,
                    'image' => $product->image,
                ],
                'current_price' => $auction->current_price,
                'bid_count'     => $bidCount,
                'status'        => $status,
                'joined_at'     => $participant->joined_at->format('d/m/Y'),
                'date_group'    => $participant->joined_at->format('Y-m-d'),
            ];
        })->filter()->values();

        // Nhóm theo ngày
        $grouped = $data->groupBy('date_group')->map(function ($items, $date) {
            return [
                'date'  => $date,
                'items' => $items->values(),
            ];
        })->values();

        return response()->json([
            'success'      => true,
            'data'         => $grouped,
            'current_page' => $participants->currentPage(),
            'last_page'    => $participants->lastPage(),
            'has_more'     => $participants->hasMorePages(),
        ]);
    }

    private function getStatus($auction): string
    {
        if ($auction->status === 'active' || $auction->status === 'pending') {
            return 'participating';
        }

        if ($auction->status === 'cancelled') {
            return 'cancelled';
        }

        if ($auction->status === 'completed') {
            if ($auction->winner_id === auth()->id()) {
                return 'won';
            }
            return 'lost';
        }

        return 'unknown';
    }
}