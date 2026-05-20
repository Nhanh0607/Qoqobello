<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BidRequest;
use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Bid;
use Illuminate\Http\JsonResponse;


class AuctionController extends Controller
{
    // Danh sách phiên đấu giá
    public function index(): JsonResponse
    {
        $auctions = Auction::with('product')
            ->whereIn('status', ['pending', 'active'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $auctions,
        ]);
    }

    // Chi tiết phiên đấu giá
    public function show(Auction $auction): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $auction->load('product', 'bids.user', 'participants.user'),
        ]);
    }

    // Tham gia phòng đấu giá
    public function join(Auction $auction): JsonResponse
    {
        $participantCount = $auction->participants()->count();

        if ($participantCount >= $auction->max_participants) {
            return response()->json([
                'success' => false,
                'message' => 'Phòng đấu giá đã đầy',
            ], 400);
        }

        $already = AuctionParticipant::where('auction_id', $auction->id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã tham gia phòng này rồi',
            ], 400);
        }

        if ($auction->status === 'completed' || $auction->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Phiên đấu giá đã kết thúc',
            ], 400);
        }

        AuctionParticipant::create([
            'auction_id' => $auction->id,
            'user_id'    => auth()->id(),
            'joined_at'  => now(),
        ]);

        $newCount = $auction->participants()->count();
        if ($auction->status === 'pending' && $newCount >= $auction->min_participants) {
            $auction->update(['status' => 'active']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tham gia phòng đấu giá thành công',
        ]);
    }

    // Đặt giá
    public function bid(BidRequest $request, Auction $auction): JsonResponse
    {
        // Kiểm tra phiên đang active không
        if ($auction->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Phiên đấu giá chưa mở hoặc đã kết thúc',
            ], 400);
        }

        // Kiểm tra đã hết giờ chưa
        if (now()->gt($auction->ended_at)) {
            // Tự động cập nhật trạng thái
            $auction->update([
                'status'           => 'completed',
                'winner_id'        => Bid::where('auction_id', $auction->id)
                                        ->orderBy('amount', 'desc')
                                        ->first()?->user_id,
                'payment_deadline' => now()->addDay(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Phiên đấu giá đã kết thúc',
            ], 400);
        }

        // Kiểm tra đã tham gia chưa
        $isParticipant = AuctionParticipant::where('auction_id', $auction->id)
            ->where('user_id', auth()->id())
            ->exists();

        if (!$isParticipant) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa tham gia phòng đấu giá này',
            ], 400);
        }

        // Kiểm tra số tiền bid
        if ($request->amount <= $auction->current_price) {
            return response()->json([
                'success' => false,
                'message' => 'Số tiền bid phải lớn hơn giá hiện tại ' . $auction->current_price,
            ], 400);
        }

        Bid::create([
            'auction_id' => $auction->id,
            'user_id'    => auth()->id(),
            'amount'     => $request->amount,
        ]);

        $auction->update(['current_price' => $request->amount]);

        return response()->json([
            'success' => true,
            'message' => 'Đặt giá thành công',
            'data'    => [
                'current_price' => $auction->current_price,
            ]
        ]);
    }

    // Mua thẳng
    public function buyNow(Auction $auction): JsonResponse
    {
        if ($auction->status === 'completed' || $auction->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Phiên đấu giá không còn hiệu lực',
            ], 400);
        }

        $auction->update([
            'status'           => 'completed',
            'winner_id'        => auth()->id(),
            'current_price'    => $auction->product->store_price,
            'payment_deadline' => now()->addDay(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mua thành công với giá ' . $auction->product->store_price,
            'data'    => [
                'price'            => $auction->product->store_price,
                'payment_deadline' => $auction->payment_deadline,
            ]
        ]);
    }
}