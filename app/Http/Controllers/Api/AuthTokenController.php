<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthTokenController extends Controller
{
    /**
     * Exchange email + password for a Sanctum personal access token.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->firstWhere('email', $request->validated('email'));

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password salah.',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $user->createToken('chat-ai')->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Revoke the token used for this request.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
