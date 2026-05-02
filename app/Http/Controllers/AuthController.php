<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    /**
     * Build the HttpOnly authentication cookie for the given token.
     */
    private function buildAuthCookie(string $token): Cookie
    {
        return cookie(
            name: 'auth_token',
            value: $token,
            minutes: (int) env('AUTH_COOKIE_LIFETIME', 60 * 24 * 30),
            path: '/',
            domain: null,
            secure: (bool) env('AUTH_COOKIE_SECURE', app()->isProduction()),
            httpOnly: true,
            sameSite: env('AUTH_COOKIE_SAMESITE', 'Lax'),
        );
    }

    /**
     * Build an expired cookie to clear the auth token cookie.
     */
    private function clearAuthCookie(): Cookie
    {
        return cookie()->forget('auth_token');
    }

    /**
     * Register a new user.
     *
     * Creates a new user account and returns an API token for immediate use.
     * Also sets an HttpOnly cookie containing the same token for web clients.
     *
     * @unauthenticated
     *
     * @response 201 {"token": "string", "user": {"id": 1, "name": "string", "email": "string"}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201)->withCookie($this->buildAuthCookie($token));
    }

    /**
     * Login.
     *
     * Authenticates the user with email and password and returns an API token.
     * Also sets an HttpOnly cookie containing the same token for web clients.
     *
     * @unauthenticated
     *
     * @response {"token": "string", "user": {"id": 1, "name": "string", "email": "string"}}
     * @response 422 {"message": "The provided credentials are incorrect."}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ])->withCookie($this->buildAuthCookie($token));
    }

    /**
     * Logout.
     *
     * Revokes the current Bearer token and clears the auth cookie.
     * Subsequent requests with this token will return 401.
     *
     * @response 200 {"message": "Successfully logged out."}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.',
        ])->withCookie($this->clearAuthCookie());
    }
}
