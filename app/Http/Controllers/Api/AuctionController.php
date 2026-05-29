<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BidRequest;
use App\Models\Auction;
use App\Models\AuctionParticipant;
use App\Models\Bid;
use App\Models\QoqoTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
    public function join(int $auctionId): JsonResponse
    {
        return DB::transaction(function () use ($auctionId) {
            $auction = Auction::with('product')->lockForUpdate()->find($auctionId);

            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá không tồn tại',
                ], 404);
            }

            // 1. Kiểm tra phiên đã kết thúc chưa
            if ($auction->status === 'completed' || $auction->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá đã kết thúc',
                ], 400);
            }

            // 2. Kiểm tra đã hết giờ chưa
            if (now()->gt($auction->ended_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá đã hết giờ',
                ], 400);
            }

            // 3. Kiểm tra phòng còn chỗ không
            $participantCount = $auction->participants()->count();
            if ($participantCount >= $auction->max_participants) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phòng đấu giá đã đầy',
                ], 400);
            }

            // 4. Kiểm tra đã tham gia chưa
            $already = AuctionParticipant::where('auction_id', $auction->id)
                ->where('user_id', auth()->id())
                ->exists();

            if ($already) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã tham gia phòng này rồi',
                ], 400);
            }

            // 5. Kiểm tra và trừ coin mở khóa
            $user = User::lockForUpdate()->find(auth()->id());

            if ($auction->unlock_cost > 0) {
                if ($user->qoqo_balance < $auction->unlock_cost) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Số coin không đủ để mở khóa phòng. Cần ' . $auction->unlock_cost . ' QOQO',
                    ], 400);
                }

                $balanceBefore = $user->qoqo_balance;
                $balanceAfter  = $balanceBefore - $auction->unlock_cost;

                $user->update(['qoqo_balance' => $balanceAfter]);

                QoqoTransaction::create([
                    'user_id'        => $user->id,
                    'type'           => 'unlock',
                    'amount'         => -$auction->unlock_cost,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'description'    => 'Mở khóa phòng đấu giá #' . $auction->id,
                    'auction_id'     => $auction->id,
                ]);
            }

            AuctionParticipant::create([
                'auction_id' => $auction->id,
                'user_id'    => $user->id,
                'joined_at'  => now(),
            ]);

            // Kiểm tra đủ người tối thiểu và đúng giờ → mở phiên
            $newCount = $auction->participants()->count();
            if ($auction->status === 'pending'
                && $newCount >= $auction->min_participants
                && now()->gte($auction->started_at)) {
                $auction->update(['status' => 'active']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tham gia phòng đấu giá thành công',
                'data'    => [
                    'balance' => $user->fresh()->qoqo_balance,
                ]
            ]);
        });
    }

    // Đặt giá
    public function bid(BidRequest $request, int $auctionId): JsonResponse
    {
        return DB::transaction(function () use ($request, $auctionId) {
            $auction = Auction::with('product')->lockForUpdate()->find($auctionId);

            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá không tồn tại',
                ], 404);
            }

            // 1. Kiểm tra phiên đang active không
            if ($auction->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá chưa mở hoặc đã kết thúc',
                ], 400);
            }

            // 2. Kiểm tra đã hết giờ chưa
            if (now()->gt($auction->ended_at)) {
                $auction->update([
                    'status'           => 'completed',
                    'winner_id'        => Bid::where('auction_id', $auction->id)
                                            ->where('type', 'bid')
                                            ->orderBy('amount', 'desc')
                                            ->first()?->user_id,
                    'payment_deadline' => now()->addDay(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá đã kết thúc',
                ], 400);
            }

            // 3. Kiểm tra đủ số người tối thiểu chưa
            $participantCount = $auction->participants()->count();
            if ($participantCount < $auction->min_participants) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá chưa đủ số người tối thiểu',
                ], 400);
            }

            // 4. Kiểm tra đã tham gia phòng chưa
            $isParticipant = AuctionParticipant::where('auction_id', $auction->id)
                ->where('user_id', auth()->id())
                ->exists();

            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa tham gia phòng đấu giá này',
                ], 400);
            }

            // 5. Kiểm tra bước giá tối thiểu
            $minValidAmount = $auction->current_price + $auction->bid_increment;
            if ($request->amount < $minValidAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số tiền bid tối thiểu là ' . $minValidAmount . ' QOQO',
                ], 400);
            }

            // 6. Kiểm tra số tiền bid không vượt quá giá cửa hàng
            $maxValidAmount = $auction->product->store_price * 100;
            if ($request->amount >= $maxValidAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số tiền bid không được vượt quá ' . $maxValidAmount . ' QOQO',
                ], 400);
            }

            // Lưu bid
            Bid::create([
                'auction_id' => $auction->id,
                'user_id'    => auth()->id(),
                'amount'     => $request->amount,
                'type'       => 'bid',
            ]);

            $auction->update(['current_price' => $request->amount]);

            return response()->json([
                'success' => true,
                'message' => 'Đặt giá thành công',
                'data'    => [
                    'current_price' => $request->amount,
                ]
            ]);
        });
    }

    // Mua thẳng
    public function buyNow(int $auctionId): JsonResponse
    {
        return DB::transaction(function () use ($auctionId) {
            $auction = Auction::with('product')->lockForUpdate()->find($auctionId);

            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá không tồn tại',
                ], 404);
            }

            // 1. Kiểm tra phiên đã kết thúc chưa
            if ($auction->status === 'completed' || $auction->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá không còn hiệu lực',
                ], 400);
            }

            // 2. Kiểm tra đã hết giờ chưa
            if (now()->gt($auction->ended_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá đã hết giờ',
                ], 400);
            }

            // 3. Kiểm tra chưa đến giờ bắt đầu
            if (now()->lt($auction->started_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá chưa bắt đầu',
                ], 400);
            }

            // 4. Kiểm tra đủ số người tối thiểu chưa
            $participantCount = $auction->participants()->count();
            if ($participantCount < $auction->min_participants) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá chưa đủ số người tối thiểu',
                ], 400);
            }

            // 5. Kiểm tra người dùng có đang là người bid cao nhất không
            $highestBid = Bid::where('auction_id', $auction->id)
                ->where('type', 'bid')
                ->orderBy('amount', 'desc')
                ->first();

            if ($highestBid && $highestBid->user_id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đang là người bid cao nhất, không cần mua thẳng',
                ], 400);
            }

            // 6. Kiểm tra đủ coin không
            $user          = User::lockForUpdate()->find(auth()->id());
            $coinsRequired = $auction->product->store_price * 100;

            if ($user->qoqo_balance < $coinsRequired) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số coin không đủ. Cần ' . $coinsRequired . ' QOQO',
                ], 400);
            }

            // 7. Trừ coin
            $balanceBefore = $user->qoqo_balance;
            $balanceAfter  = $balanceBefore - $coinsRequired;
            $user->update(['qoqo_balance' => $balanceAfter]);

            // 8. Lưu transaction
            QoqoTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'payment',
                'amount'         => -$coinsRequired,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => 'Mua thẳng sản phẩm ' . $auction->product->title,
                'auction_id'     => $auction->id,
            ]);

            // 9. Lưu bid buy_now
            Bid::create([
                'auction_id' => $auction->id,
                'user_id'    => $user->id,
                'amount'     => $coinsRequired,
                'type'       => 'buy_now',
            ]);

            $auction->update([
                'status'           => 'completed',
                'winner_id'        => $user->id,
                'current_price'    => $auction->product->store_price,
                'payment_deadline' => now()->addDay(),
                'is_paid'          => true, // Mua thẳng = đã thanh toán luôn
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mua thành công',
                'data'    => [
                    'price'   => $coinsRequired,
                    'balance' => $balanceAfter,
                ]
            ]);
        });
    }

    // Thanh toán sau khi thắng đấu giá
    public function pay(int $auctionId): JsonResponse
    {
        return DB::transaction(function () use ($auctionId) {
            $auction = Auction::with('product')->lockForUpdate()->find($auctionId);

            if (!$auction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá không tồn tại',
                ], 404);
            }

            // 1. Kiểm tra user có phải winner không
            if ($auction->winner_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không phải người thắng phiên đấu giá này',
                ], 403);
            }

            // 2. Kiểm tra phiên đã completed chưa
            if ($auction->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đấu giá chưa kết thúc',
                ], 400);
            }

            // 3. Kiểm tra đã thanh toán chưa
            if ($auction->is_paid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã thanh toán rồi',
                ], 400);
            }

            // 4. Kiểm tra hết hạn thanh toán chưa
            if (now()->gt($auction->payment_deadline)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đã hết hạn thanh toán',
                ], 400);
            }

            // 5. Tính số coin cần thanh toán
            // current_price lưu bằng € → nhân 100 ra coin
            $user          = User::lockForUpdate()->find(auth()->id());
            $coinsRequired = $auction->current_price * 100;

            // 6. Kiểm tra đủ coin không
            if ($user->qoqo_balance < $coinsRequired) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số coin không đủ. Cần ' . $coinsRequired . ' QOQO',
                ], 400);
            }

            // 7. Trừ coin
            $balanceBefore = $user->qoqo_balance;
            $balanceAfter  = $balanceBefore - $coinsRequired;
            $user->update(['qoqo_balance' => $balanceAfter]);

            // 8. Lưu transaction
            QoqoTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'payment',
                'amount'         => -$coinsRequired,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => 'Thanh toán sản phẩm ' . $auction->product->title,
                'auction_id'     => $auction->id,
            ]);

            // 9. Đánh dấu đã thanh toán
            $auction->update(['is_paid' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Thanh toán thành công',
                'data'    => [
                    'balance' => $balanceAfter,
                ]
            ]);
        });
    }

    // Danh sách phiên đấu giá nhóm theo ngày
    public function byDate(): JsonResponse
    {
        $auctions = Auction::with('product')
            ->whereIn('status', ['pending', 'active'])
            ->orderBy('started_at')
            ->get();

        $data = $auctions->map(function ($auction) {
            // Kiểm tra product tồn tại
            if (!$auction->product) return null;

            $participantCount = $auction->participants()->count();

            $isJoined = AuctionParticipant::where('auction_id', $auction->id)
                ->where('user_id', auth()->id())
                ->exists();

            return [
                'id'               => $auction->id,
                'product'          => [
                    'name'        => $auction->product->title,
                    'image'       => $auction->product->image,
                    'store_price' => $auction->product->store_price,
                ],
                'current_price'    => $auction->current_price,
                'unlock_cost'      => $auction->unlock_cost,
                'status'           => $auction->status,
                'participants'     => $participantCount,
                'max_participants' => $auction->max_participants,
                'is_joined'        => $isJoined,
                'started_at'       => $auction->started_at,
                'ended_at'         => $auction->ended_at,
                'date_group'       => $auction->started_at->format('Y-m-d'),
            ];
        })->filter()->values();

        $grouped = $data->groupBy('date_group')->map(function ($items, $date) {
            $label = match(true) {
                $date === now()->format('Y-m-d')           => 'Aujourd\'hui',
                $date === now()->addDay()->format('Y-m-d') => 'Demain',
                $date === now()->subDay()->format('Y-m-d') => 'Hier',
                default                                     => $date,
            };

            return [
                'date'  => $date,
                'label' => $label,
                'items' => $items->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $grouped,
        ]);
    }
}