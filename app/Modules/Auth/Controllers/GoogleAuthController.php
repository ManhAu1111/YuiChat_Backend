<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Xác thực token Google từ Client (Vue/Flutter) và trả về user + Sanctum token.
     */
    public function verifyGoogleToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->userFromToken($request->token);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid or expired Google token.',
                'error' => $e->getMessage(),
            ], 401);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->google_id = $googleUser->getId();
            if ($user->avatar !== $googleUser->getAvatar()) {
                $user->avatar = $googleUser->getAvatar();
            }
            $user->save();
        } else {
            $user = $this->createUserFromGoogle($googleUser);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Tạo user mới từ thông tin Google. Username sinh từ email (prefix_random) để tránh trùng.
     */
    private function createUserFromGoogle($googleUser): User
    {
        $email = $googleUser->getEmail();
        $prefix = Str::before($email, '@');
        $username = $prefix . '_' . Str::lower(Str::random(6));

        while (User::where('username', $username)->exists()) {
            $username = $prefix . '_' . Str::lower(Str::random(6));
        }

        return User::create([
            'google_id' => $googleUser->getId(),
            'email' => $email,
            'name' => $googleUser->getName() ?? $prefix,
            'avatar' => $googleUser->getAvatar(),
            'username' => $username,
            'password' => null,
        ]);
    }
}
