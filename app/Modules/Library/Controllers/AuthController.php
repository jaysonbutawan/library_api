<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Library\Models\LibraryMember;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    protected $admissionApiUrl;
    protected $admissionApiToken;

    public function __construct()
    {
        $this->admissionApiUrl = config('services.admission.url'); // e.g., https://admission.example.com
        $this->admissionApiToken = config('services.admission.token'); // optional API token
    }

    /**
     * Library login (auto-registers member if first login)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        // Validate student credentials via Admission API
        $response = Http::withToken($this->admissionApiToken)
            ->post("{$this->admissionApiUrl}/api/admission/login", [
                'email' => $request->email,
                'password' => $request->password
            ]);

        if (!$response->successful()) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        $studentData = $response->json();

        // Store or update student info locally
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

        // Create local library token
        $token = $member->createToken('library-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'student' => [
                'id' => $member->student_id,
                'name' => $member->full_name,
                'department' => $member->department,
                'email' => $member->email,
                'membership_status' => $member->membership_status
            ]
        ]);
    }

    /**
     * Get logged-in student info (protected route)
     */
    public function profile(Request $request)
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'message' => 'Library member not found'
            ], 404);
        }

        // Optionally refresh from Admission API
        $studentData = null;
        try {
            $response = Http::withToken($this->admissionApiToken)
                ->get("{$this->admissionApiUrl}/api/students/{$member->student_id}");

            if ($response->successful()) {
                $studentData = $response->json();
            }
        } catch (\Exception $e) {
            // Fallback: use locally stored info
        }

        return response()->json([
            'student' => [
                'id' => $member->student_id,
                'name' => $studentData['full_name'] ?? $member->full_name,
                'department' => $studentData['department'] ?? $member->department,
                'email' => $studentData['email'] ?? $member->email,
                'membership_status' => $member->membership_status
            ]
        ]);
    }
}