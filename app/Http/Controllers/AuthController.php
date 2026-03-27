<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login with admission credentials
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'student_id' => 'required|string',
                'password' => 'required|string',
            ]);

            $result = $this->authService->login($credentials);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'user' => $result['user'],
                    'token' => $result['token'],
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login.',
            ], 500);
        }
    }

    /**
     * Get current user profile
     * GET /api/auth/profile
     * Requires: Sanctum authentication
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $result = $this->authService->profile($user);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Profile endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching profile.',
            ], 500);
        }
    }

    /**
     * Get current user profile with library statistics
     * GET /api/auth/profile/stats
     * Requires: Sanctum authentication
     */
    public function profileWithStats(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $result = $this->authService->profileWithStats($user);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Profile with stats endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching profile.',
            ], 500);
        }
    }

    /**
     * Update user profile
     * PATCH /api/auth/profile
     * Requires: Sanctum authentication
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'department' => 'sometimes|string|max:255',
            ]);

            $result = $this->authService->updateProfile($user, $validated);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Update profile endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating profile.',
            ], 500);
        }
    }

    /**
     * Logout (revoke current token)
     * POST /api/auth/logout
     * Requires: Sanctum authentication
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $result = $this->authService->logout($user);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Logout endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
            ], 500);
        }
    }

    /**
     * Logout from all devices (revoke all tokens)
     * POST /api/auth/logout-all
     * Requires: Sanctum authentication
     */
    public function logoutAllDevices(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $result = $this->authService->logoutAllDevices($user);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Logout all devices endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
            ], 500);
        }
    }

    /**
     * Check if current user is eligible for borrowing
     * GET /api/auth/eligibility
     * Requires: Sanctum authentication
     */
    public function checkEligibility(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $isEligible = $this->authService->isEligibleForBorrowing($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'student_id' => $user->student_id,
                    'status' => $user->status,
                    'eligible_for_borrowing' => $isEligible,
                    'message' => $isEligible
                        ? 'You are eligible to borrow books.'
                        : "You cannot borrow books. Your account status is: {$user->status}"
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Eligibility check endpoint error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while checking eligibility.',
            ], 500);
        }
    }
}
