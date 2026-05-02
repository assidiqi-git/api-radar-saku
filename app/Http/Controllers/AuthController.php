<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Determine whether the request originates from a web (SPA) client.
     *
     * Detection is based on the X-Client-Type request header.
     * Defaults to mobile if the header is absent (backward-compatible).
     */
    private function isWebClient(Request $request): bool
    {
        return strtolower((string) $request->header('X-Client-Type')) === 'web';
    }

    /**
     * Register a new user.
     *
     * - Web clients (X-Client-Type: web): returns 201 with user data only.
     *   The session cookie is set automatically by Laravel's session handling.
     * - Mobile clients: returns 201 with user data and a plaintext Bearer token.
     *
     * @unauthenticated
     *
     * @response 201 {"user": {"id": "string", "name": "string", "email": "string"}}
     * @response 201 {"token": "string", "user": {"id": "string", "name": "string", "email": "string"}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($this->isWebClient($request)) {
            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return response()->json(['user' => $user], 201);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Login.
     *
     * Authenticates the user with email and password.
     *
     * - Web clients (X-Client-Type: web): returns 204 No Content.
     *   The HTTP-only session cookie is set automatically by Sanctum's SPA authentication.
     * - Mobile clients: returns user data and a plaintext Bearer token.
     *
     * @unauthenticated
     *
     * @response 204
     * @response {"token": "string", "user": {"id": "string", "name": "string", "email": "string"}}
     * @response 422 {"message": "The provided credentials are incorrect."}
     */
    public function login(LoginRequest $request): JsonResponse|Response
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        if ($this->isWebClient($request)) {
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            return response()->noContent();
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Logout.
     *
     * - Web clients (X-Client-Type: web): invalidates the session.
     * - Mobile clients: revokes the current Bearer token.
     *
     * @response 200 {"message": "Successfully logged out."}
     */
    public function logout(Request $request): JsonResponse
    {
        if ($this->isWebClient($request)) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } else {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Successfully logged out.']);
    }
}
