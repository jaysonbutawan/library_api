<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class StaffAuthService
{
    public function login(array $credentials): array
    {
        // 1. Clean Inputs
        $email = strtolower(trim($credentials['email'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if (!$email || !$password) {
            return [
                'success' => false,
                'message' => 'Email and password are required.',
            ];
        }

        $user = User::with('role')->where('email', $email)->first();

        // 3. Check if User Exists
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
            ];
        }

        // 4. Role Check using your Model Helpers
        // This is safer than manual in_array checks
        if (!$user->isLibrarian() && !$user->isAssistant() && !$user->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Unauthorized account type. Access restricted to staff.',
            ];
        }

        // 5. Account Status Check
        if (!$user->isActive()) {
            return [
                'success' => false,
                'message' => 'Your account is currently ' . $user->status . '. Please contact admin.',
            ];
        }

        // 6. Password Verification
        if (!Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
            ];
        }

        // 7. Update Last Login (forceFill bypasses mass-assignment if needed)
        $user->forceFill(['last_login' => now()])->save();

        // 8. Generate Sanctum Token
        $token = $user->createToken('library-staff-token')->plainTextToken;

        return [
            'success' => true,
            'user' => $user->load('role'), // Ensure role name is sent to Angular
            'token' => $token,
        ];
    }

    public function changePassword(User $user, array $data): array
    {
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmPassword = (string) ($data['new_password_confirmation'] ?? '');

        // 1. Validate inputs
        if (!$currentPassword || !$newPassword) {
            return [
                'success' => false,
                'message' => 'All fields are required.',
            ];
        }

        // 2. Check current password
        if (!Hash::check($currentPassword, $user->password)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect.',
            ];
        }

        // 3. Confirm password match
        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'message' => 'New password does not match confirmation.',
            ];
        }

        // 4. Prevent reuse
        if (Hash::check($newPassword, $user->password)) {
            return [
                'success' => false,
                'message' => 'New password must be different from current password.',
            ];
        }

        // 5. Update password
        $user->password = Hash::make($newPassword);
        $user->save();

        return [
            'success' => true,
            'message' => 'Password changed successfully.',
        ];
    }
}
