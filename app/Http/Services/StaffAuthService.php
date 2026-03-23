<?php

namespace App\Modules\Library\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StaffAuthService
{
    public function login(array $credentials): array
    {
        $email = strtolower(trim($credentials['email'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if (!$email || !$password) {
            return [
                'success' => false,
                'message' => 'Email and password are required.',
            ];
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
            ];
        }

        // Must be staff roles only
        if (!in_array($user->role, ['librarian', 'assistant'], true)) {
            return [
                'success' => false,
                'message' => 'Unauthorized account type.',
            ];
        }

        // Must be active
        if ($user->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Account is not active.',
            ];
        }

        // Staff must have password set
        if (!$user->password || !Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
            ];
        }

        // Optional: clear old tokens if you want 1-session policy
        // $user->tokens()->delete();

        $user->forceFill(['last_login' => now()])->save();

        $token = $user->createToken('library-staff-token')->plainTextToken;

        return [
            'success' => true,
            'user' => $user,
            'token' => $token,
        ];
    }
}