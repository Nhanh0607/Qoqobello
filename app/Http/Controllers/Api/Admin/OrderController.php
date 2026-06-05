<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Thứ tự trạng thái hợp lệ
    private array $statusFlow = [
        'pending'    => ['processing', 'cancelled'],
        'processing' => ['shipping', 'cancelled'],
        'shipping'   => ['delivered'],
        'delivered'  => [],
        'cancelled'  => [],
    ];

    // Danh sách tất cả đơn hàng
    public function index(): JsonResponse
    {
        $orders = Order::with(['user', 'product', 'auction'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success'      => true,
            'data'         => $orders->items(),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'has_more'     => $orders->hasMorePages(),
        ]);
    }

    // Cập nhật trạng thái đơn hàng
    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): JsonResponse
    {
        return DB::transaction(function () use ($request, $orderId) {
            $order = Order::lockForUpdate()->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng không tồn tại',
                ], 404);
            }

            // Kiểm tra trạng thái hiện tại có thể chuyển sang trạng thái mới không
            $allowedStatuses = $this->statusFlow[$order->status] ?? [];

            if (!in_array($request->status, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể chuyển từ trạng thái "' . $order->status . '" sang "' . $request->status . '"',
                    'allowed' => $allowedStatuses,
                ], 400);
            }

            $order->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data'    => $order->fresh()->load('product', 'user'),
            ]);
        });
    }
}