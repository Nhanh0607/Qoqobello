<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAuctionRequest;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;

class AuctionController extends Controller
{
    public function index(): JsonResponse
    {
        $auctions = Auction::with('product', 'winner')->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $auctions,
        ]);
    }

    public function store(CreateAuctionRequest $request): JsonResponse
    {
        $auction = Auction::create([
            'product_id'       => $request->product_id,
            'start_price'      => $request->start_price,
            'current_price'    => $request->start_price,
            'bid_increment'    => $request->bid_increment,
            'unlock_cost'      => $request->unlock_cost,
            'min_participants' => $request->min_participants,
            'max_participants' => $request->max_participants,
            'started_at'       => $request->started_at,
            'ended_at'         => $request->ended_at,
            'status'           => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo phiên đấu giá thành công',
            'data'    => $auction->load('product'),
        ], 201);
    }
}