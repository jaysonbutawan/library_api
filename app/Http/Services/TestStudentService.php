<?php
namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class TestStudentService
{
   public function testLogin(): array
{
    try {
        // We use a fixed ID for testing so you don't clutter your DB with 1000s of tests
        $fakeStudentId = 'TEST-9999';

        $user = User::updateOrCreate(
            ['student_id' => $fakeStudentId],
            [
                'full_name'     => 'Test Student (Fake)',
                'email'         => 'test.student@example.com',
                'department'    => 'Testing Department',
                'status'        => 'active',
                'role'          => 'student',
                'password'      => null,
                'registered_at' => now(),
            ]
        );

        // Generate a real token for this fake user
        $token = $user->createToken('test-library-token')->plainTextToken;

    } catch (\Exception $e) {
        // SILENT FAIL: If DB fails, create a purely virtual user for the UI to read
        Log::warning('Database failed, providing virtual mock user.');

        $token = "mock_token_for_ui_testing_only";
        $user = (object)[
            'id' => 999,
            'student_id' => 'VIRTUAL-001',
            'full_name' => 'Virtual Test User',
            'email' => 'virtual@test.com',
            'department' => 'Mock Dept',
            'status' => 'active',
            'registered_at' => now(),
        ];
    }

    return [
        'success' => true, // Always true for this test route
        // 'message' => 'Fake Login Bypass Active.',
        'message' => 'Login Successful.',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'student_id' => $user->student_id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'department' => $user->department,
                'status' => $user->status,
                'registered_at' => $user->registered_at,
            ],
        ]
    ];
}
}
