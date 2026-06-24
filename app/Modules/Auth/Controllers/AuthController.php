<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegisterOtpMail;
use App\Mail\ForgotPasswordOtpMail;

class AuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $otp = sprintf('%06d', mt_rand(0, 999999));
        
        Cache::put("otp_register_{$request->email}", $otp, now()->addMinutes(10));
        
        Mail::to($request->email)->send(new RegisterOtpMail($otp));

        return response()->json(['message' => 'Mã OTP đã được gửi.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $cachedOtp = Cache::get("otp_register_{$request->email}");

        if (! $cachedOtp || $cachedOtp !== $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['Mã OTP không đúng hoặc đã hết hạn.'],
            ]);
        }

        Cache::put("otp_verified_{$request->email}", true, now()->addMinutes(15));
        Cache::forget("otp_register_{$request->email}");

        return response()->json(['message' => 'Xác thực OTP thành công.']);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if (! Cache::get("otp_verified_{$validated['email']}")) {
            throw ValidationException::withMessages([
                'email' => ['Email chưa được xác thực OTP.'],
            ]);
        }

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'name' => $validated['name'],
        ]);

        Cache::forget("otp_verified_{$validated['email']}");

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function sendForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'exists:users,email'],
        ]);

        $otp = sprintf('%06d', mt_rand(0, 999999));
        
        Cache::put("otp_forgot_{$request->email}", $otp, now()->addMinutes(10));
        
        Mail::to($request->email)->send(new ForgotPasswordOtpMail($otp));

        return response()->json(['message' => 'Mã OTP khôi phục mật khẩu đã được gửi.']);
    }

    public function verifyForgotPasswordOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $cachedOtp = Cache::get("otp_forgot_{$request->email}");

        if (! $cachedOtp || $cachedOtp !== $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['Mã OTP không đúng hoặc đã hết hạn.'],
            ]);
        }

        Cache::put("otp_forgot_verified_{$request->email}", true, now()->addMinutes(15));
        Cache::forget("otp_forgot_{$request->email}");

        return response()->json(['message' => 'Xác thực OTP thành công.']);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Cache::get("otp_forgot_verified_{$validated['email']}")) {
            throw ValidationException::withMessages([
                'email' => ['Email chưa được xác thực OTP.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->password = Hash::make($validated['password']);
        $user->save();

        Cache::forget("otp_forgot_verified_{$validated['email']}");

        return response()->json([
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }
}
