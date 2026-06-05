<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\QoqoTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Danh sách đơn hàng của user
    public function index(): JsonResponse
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['product', 'auction'])
            ->latest()
            ->paginate(10);

        $data = collect($orders->items())->map(function ($order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'success'      => true,
            'data'         => $data,
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'has_more'     => $orders->hasMorePages(),
        ]);
    }

    // Chi tiết đơn hàng
    public function show(int $orderId): JsonResponse
    {
        $order = Order::where('user_id', auth()->id())
            ->with(['product', 'auction'])
            ->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng không tồn tại',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($order),
        ]);
    }

    // Hủy đơn hàng
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        // Validate reason
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $orderId) {
            $order = Order::where('user_id', auth()->id())
                ->lockForUpdate()
                ->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng không tồn tại',
                ], 404);
            }

            // Chỉ hủy được khi pending
            if ($order->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể hủy đơn hàng ở trạng thái chờ xử lý',
                ], 400);
            }

            // Hoàn coin
            $user          = User::lockForUpdate()->find(auth()->id());
            $balanceBefore = $user->qoqo_balance;
            $balanceAfter  = $balanceBefore + $order->amount;

            $user->update(['qoqo_balance' => $balanceAfter]);

            // Lưu transaction hoàn tiền
            QoqoTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'refund',
                'amount'         => $order->amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => 'Hoàn tiền đơn hàng #' . $order->id,
                'auction_id'     => $order->auction_id,
            ]);

            // Cập nhật đơn hàng
            $order->update([
                'status'           => 'cancelled',
                'cancelled_at'     => now(),
                'cancelled_reason' => $request->reason ?? 'User hủy đơn',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hủy đơn hàng thành công',
                'data'    => [
                    'balance' => $balanceAfter,
                ]
            ]);
        });
    }

    // Format response đơn hàng
    private function formatOrder(Order $order): array
    {
        return [
            'id'         => $order->id,
            'status'     => $order->status,
            'amount'     => $order->amount,
            'product'    => [
                'id'    => $order->product->id,
                'name'  => $order->product->title,
                'image' => $order->product->image,
            ],
            'auction'    => [
                'id' => $order->auction->id,
            ],
            'address'    => [
                'street'        => $order->street,
                'street_number' => $order->street_number,
                'city'          => $order->city,
                'postal_code'   => $order->postal_code,
                'country'       => $order->country,
            ],
            'cancelled_at'     => $order->cancelled_at?->format('d/m/Y H:i'),
            'cancelled_reason' => $order->cancelled_reason,
            'created_at'       => $order->created_at->format('d/m/Y H:i'),
        ];
    }
}