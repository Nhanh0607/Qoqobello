<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;


class ProfileController extends Controller
{
    // Xem thông tin profile
    public function index(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'avatar'       => $user->avatar,
                'provider'     => $user->provider,
                'qoqo_balance' => $user->qoqo_balance,
                'address'      => [
                    'street'        => $user->street,
                    'street_number' => $user->street_number,
                    'city'          => $user->city,
                    'postal_code'   => $user->postal_code,
                    'country'       => $user->country,
                ],
                'payment'      => [
                    'card_holder_name' => $user->card_holder_name,
                    // Ẩn số thẻ chỉ hiện 4 số cuối
                    'card_number'      => $user->card_number
                        ? '****' . substr($user->card_number, -4)
                        : null,
                    'card_expiry'      => $user->card_expiry,
                    // Không trả về card_ccv vì lý do bảo mật
                ],
                'created_at'   => $user->created_at->format('d/m/Y'),
            ]
        ]);
    }

    // Cập nhật profile
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        // Sử dụng luôn auth()->user() để tránh query thừa
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Lấy dữ liệu và lọc bỏ các giá trị null
        $data = array_filter($request->only([
            'name',
            'street',
            'street_number',
            'city',
            'postal_code',
            'country',
            'card_holder_name',
            'card_number',
            'card_expiry',
            'card_ccv',
        ]), fn($value) => !is_null($value));

        // Xử lý upload avatar nếu có
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Cập nhật thông tin user
        $user->update($data);
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công',
            'data'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'avatar'       => $user->avatar,
                'qoqo_balance' => $user->qoqo_balance,
                'address'      => [
                    'street'        => $user->street,
                    'street_number' => $user->street_number,
                    'city'          => $user->city,
                    'postal_code'   => $user->postal_code,
                    'country'       => $user->country,
                ],
                'payment'      => [
                    'card_holder_name' => $user->card_holder_name,
                    'card_number'      => $user->card_number
                        ? '****' . substr($user->card_number, -4)
                        : null,
                    'card_expiry'      => $user->card_expiry,
                ],
            ]
        ]);
    }

    // Đổi mật khẩu
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Kiểm tra user đăng nhập bằng Google không
        if ($user->provider === 'google') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản Google không thể đổi mật khẩu',
            ], 400);
        }

        // Kiểm tra mật khẩu hiện tại
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không chính xác',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Xóa tất cả token cũ → bắt đăng nhập lại
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công, vui lòng đăng nhập lại',
        ]);
    }
}