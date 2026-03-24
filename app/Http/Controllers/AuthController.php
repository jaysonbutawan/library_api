<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 401);
        }

        return response()->json([
            'message' => 'Login successful.',
            'token' => $result['token'],
            'student' => [
                'id' => $result['member']->student_id,
                'name' => $result['member']->full_name,
                'department' => $result['member']->department,
                'email' => $result['member']->email,
                'membership_status' => $result['member']->membership_status
            ]
        ]);
    }

    public function profile(Request $request)
    {
        $member = $request->user();

        if (!$member) {
            return response()->json(['message' => 'Library member not found'], 404);
        }

        return response()->json([
            'student' => $this->authService->profile($member)
        ]);
    }
}