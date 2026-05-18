<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Desktop\DesktopLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class DesktopAuthController extends Controller
{
    public function login(DesktopLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages([
                'email' => [__('Two-factor authentication is enabled. Sign in via the web app to use the desktop tray.')],
            ]);
        }

        $deviceName = $request->string('device_name')->toString() ?: 'structural-desktop';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token === null && $request->bearerToken() !== null) {
            $token = PersonalAccessToken::findToken($request->bearerToken());
        }

        $token?->delete();

        return response()->json(['message' => __('Logged out.')]);
    }
}
