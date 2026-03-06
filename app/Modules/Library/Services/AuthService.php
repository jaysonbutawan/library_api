<?php

namespace App\Modules\Library\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected string $admissionApiUrl;
    protected string $admissionApiToken;

    public function __construct()
    {
        $this->admissionApiUrl = config('services.admission.url');
        $this->admissionApiToken = config('services.admission.token');
    }

    public function login(array $credentials): array
    {
        $response = Http::withToken($this->admissionApiToken)
            ->post("{$this->admissionApiUrl}/api/auth/login", $credentials);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
            ];
        }

        $studentData = $response->json();

        $member = User::updateOrCreate(
            ['student_id' => $studentData['student_id']],
            [
                'status' => 'active',                
                'registered_at' => now(),
                'full_name' => $studentData['full_name'] ?? null,
                'department' => $studentData['department'] ?? null,
                'email' => $studentData['email'] ?? null,
                'role' => 'student',                 
                'password' => null,                 
            ]
        );

        $token = $member->createToken('library-token')->plainTextToken;

        return [
            'success' => true,
            'member' => $member,
            'token' => $token,
        ];
    }

    public function profile(User $member): array
    {
        $studentData = null;

        try {
            $response = Http::withToken($this->admissionApiToken)
                ->get("{$this->admissionApiUrl}/api/students/{$member->student_id}");

            if ($response->successful()) {
                $studentData = $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch student data: ' . $e->getMessage());
        }

        return [
            'id' => $member->student_id,
            'name' => $studentData['full_name'] ?? $member->full_name,
            'department' => $studentData['department'] ?? $member->department,
            'email' => $studentData['email'] ?? $member->email,
            'status' => $member->status, 
            'role' => $member->role,
        ];
    }
}