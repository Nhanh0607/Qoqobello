<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginPinRequest;
use App\Http\Requests\SetupPinRequest;
use App\Http\Resources\UserResource;
use App\Models\UserPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PinController extends Controller
{
    // Đặt PIN
    public function setup(SetupPinRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = auth()->user();

            // Kiểm tra user đã có PIN trên thiết bị này chưa
            $existingPin = UserPin::where('user_id', $user->id)
                ->where('device_name', $request->device_name)
                ->first();

            if ($existingPin) {
                // Cập nhật PIN cũ
                $existingPin->update([
                    'pin'           => Hash::make($request->pin),
                    'attempt_count' => 0,
                    'is_locked'     => false,
                    'locked_at'     => null,
                ]);

                return response()->json([
                    'success'   => true,
                    'message'   => 'Cập nhật PIN thành công',
                    'device_id' => $existingPin->device_id,
                ]);
            }

            // Tạo PIN mới với device_id unique
            $userPin = UserPin::create([
                'user_id'     => $user->id,
                'device_id'   => Str::uuid()->toString(),
                'device_name' => $request->device_name,
                'pin'         => Hash::make($request->pin),
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Đặt PIN thành công',
                'device_id' => $userPin->device_id,
            ], 201);
        });
    }

    // Đăng nhập bằng PIN
    public function login(LoginPinRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $userPin = UserPin::where('device_id', $request->device_id)
                ->with('user')
                ->lockForUpdate()
                ->first();

            // Kiểm tra device tồn tại
            if (!$userPin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thiết bị không tồn tại hoặc chưa đặt PIN',
                ], 404);
            }

            // Kiểm tra PIN bị khóa không
            if ($userPin->is_locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'PIN đã bị khóa do nhập sai quá 5 lần. Vui lòng đăng nhập bằng mật khẩu để mở khóa',
                ], 403);
            }

            // Kiểm tra PIN đúng không
            if (!Hash::check($request->pin, $userPin->pin)) {
                $attemptCount = $userPin->attempt_count + 1;
                $isLocked     = $attemptCount >= 5;

                $userPin->update([
                    'attempt_count' => $attemptCount,
                    'is_locked'     => $isLocked,
                    'locked_at'     => $isLocked ? now() : null,
                ]);

                if ($isLocked) {
                    return response()->json([
                        'success' => false,
                        'message' => 'PIN đã bị khóa do nhập sai quá 5 lần. Vui lòng đăng nhập bằng mật khẩu để mở khóa',
                    ], 403);
                }

                return response()->json([
                    'success'            => false,
                    'message'            => 'PIN không chính xác. Còn ' . (5 - $attemptCount) . ' lần thử',
                    'attempts_remaining' => 5 - $attemptCount,
                ], 401);
            }

            // PIN đúng → reset attempt count
            $userPin->update([
                'attempt_count' => 0,
                'is_locked'     => false,
                'locked_at'     => null,
            ]);

            // Tạo token mới cho thiết bị này
            $user  = $userPin->user;
            $token = $user->createToken('pin_' . $request->device_id)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'data'    => [
                    'user'       => new UserResource($user),
                    'token'      => $token,
                    'token_type' => 'Bearer',
                ]
            ]);
        });
    }

    // Xóa PIN khỏi thiết bị hiện tại
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['device_id' => 'required|string']);

        $deleted = UserPin::where('user_id', auth()->id())
            ->where('device_id', $request->device_id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy PIN cho thiết bị này',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa PIN thành công',
        ]);
    }

    // Mở khóa PIN sau khi đăng nhập bằng password
    public function unlock(Request $request): JsonResponse
    {
        $request->validate(['device_id' => 'required|string']);

        $userPin = UserPin::where('user_id', auth()->id())
            ->where('device_id', $request->device_id)
            ->first();

        if (!$userPin) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy PIN cho thiết bị này',
            ], 404);
        }

        if (!$userPin->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'PIN không bị khóa',
            ], 400);
        }

        $userPin->update([
            'attempt_count' => 0,
            'is_locked'     => false,
            'locked_at'     => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã mở khóa PIN thành công',
        ]);
    }
}