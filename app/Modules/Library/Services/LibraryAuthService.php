<?php

namespace App\Modules\Library\Services;

use App\Modules\Library\Models\LibraryMember;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LibraryAuthService
{
    protected string $admissionApiUrl;
    protected string $admissionApiToken;

    public function __construct()
    {
        $this->admissionApiUrl = config('services.admission.url');
        $this->admissionApiToken = config('services.admission.token');
    }

    /**
     * Login a student and register them if first time
     */
    public function login(array $credentials): array
    {
        $response = Http::withToken($this->admissionApiToken)
            ->post("{$this->admissionApiUrl}/api/admission/login", $credentials);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.'
            ];
        }

        $studentData = $response->json();

        // Create or update library member
        $member = LibraryMember::updateOrCreate(
            ['student_id' => $studentData['student_id']],
            [
                'membership_status' => 'active',
                'registered_at' => now(),
                'full_name' => $studentData['full_name'],
                'department' => $studentData['department'],
                'email' => $studentData['email'],
            ]
        );

        $token = $member->createToken('library-token')->plainTextToken;

        return [
            'success' => true,
            'member' => $member,
            'token' => $token
        ];
    }

    /**
     * Fetch student profile
     */
    public function profile(LibraryMember $member): array
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
            'membership_status' => $member->membership_status
        ];
    }
}