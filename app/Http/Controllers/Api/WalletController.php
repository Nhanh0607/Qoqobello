<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyCoinsRequest;
use App\Models\QoqoTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'balance' => $user->qoqo_balance,
            ]
        ]);
    }

    public function buy(BuyCoinsRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->find(auth()->id());

            $coinsToAdd    = $request->amount_eur * 100;
            $balanceBefore = $user->qoqo_balance;
            $balanceAfter  = $balanceBefore + $coinsToAdd;

            $user->update(['qoqo_balance' => $balanceAfter]);

            QoqoTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'purchase',
                'amount'         => $coinsToAdd,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => 'Mua ' . $coinsToAdd . ' QOQO với ' . $request->amount_eur . '€',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mua coin thành công',
                'data'    => [
                    'coins_added' => $coinsToAdd,
                    'balance'     => $balanceAfter,
                ]
            ]);
        });
    }

    public function transactions(): JsonResponse
    {
        $transactions = QoqoTransaction::where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return response()->json([
            'success'      => true,
            'data'         => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'last_page'    => $transactions->lastPage(),
            'has_more'     => $transactions->hasMorePages(),
        ]);
    }
}