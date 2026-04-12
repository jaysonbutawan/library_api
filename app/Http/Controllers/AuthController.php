<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Services\StaffAuthService;
use App\Http\Services\StudentAuthService;
use App\Http\Services\TestStudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $staffAuthService;
    public function __construct(StaffAuthService $staffAuthService)
    {
        $this->staffAuthService = $staffAuthService;
    }


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

    public function me(): JsonResponse
    {
        $user = auth()->user->load('role');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    public function logout(Request $request, StudentAuthService $service)
    {
        return response()->json($service->logout());
    }
    
    public function changePassword(Request $request)
    {
        return response()->json(
            $this->staffAuthService->changePassword(
                $request->user(),
                $request->all()
            )
        );
    }
}
