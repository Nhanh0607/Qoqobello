<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'provider' => 'email',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công',
            'data'    => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác',
            ], 401);
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data'    => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công',
        ]);
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function googleCallback(): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xác thực Google thất bại',
            ], 400);
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'             => $googleUser->getName(),
                'email'            => $googleUser->getEmail(),
                'avatar'           => $googleUser->getAvatar(),
                'provider'         => 'google',
                'email_verified_at' => now(),
            ]
        );

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập Google thành công',
            'data'    => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }
}