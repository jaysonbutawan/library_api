<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Services\StaffAuthService;
use App\Http\Services\StudentAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
     public function login(LoginRequest $request): JsonResponse
    {
        $type = $request->input('type');

        Log::info('Login attempt', [
            'type' => $type,
            'ip'   => $request->ip(),
        ]);

        // Resolve only the service that's actually needed
        $result = match ($type) {
            'staff'   => app(StaffAuthService::class)->login(
                $request->only('email', 'password')
            ),
            'student' => app(StudentAuthService::class)->login(
                $request->only('email', 'password')
            ),
            default   => [
                'success' => false,
                'message' => 'Invalid account type.',
            ],
        };

        $statusCode = $result['success'] ? 200 : 401;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? ($result['success'] ? 'Login successful.' : 'Login failed.'),
            'data'    => $result['success'] ? [
                'token' => $result['token'],
                'user'  => $result['user'],
            ] : null,
        ], $statusCode);
    }

    public function logout(): JsonResponse
    {
        $user = auth()->user;

        if ($user) {
            $user->currentAccessToken()->delete();

            Log::info('User logged out', [
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user->load('role');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }
}
