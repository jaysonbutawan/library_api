<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\StaffAuthService;
use Illuminate\Http\Request;
use App\Http\Requests\StaffLoginRequest;

class StaffAuthController extends Controller
{
    public function login(StaffLoginRequest $request, StaffAuthService $service)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $service->login($data);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 401);
        }

        return response()->json($result);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }
}