<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class StudentAuthService
{
    protected string $admissionApiUrl;

    public function __construct(bool $isTest = false)
    {
        $url = config('services.admission.url');

        if (!$url) {
            throw new \RuntimeException(
                'Admission API is not configured. Check ADMISSION_API_URL in .env'
            );
        }

        $this->admissionApiUrl = $url;

        if ($isTest) {
            $this->admissionApiUrl = 'http://fake-local-url';
        }
    }
    public function login(array $credentials): array
    {
        // ─── 1. Call Admission API ─────────────────────────────────────────────
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withoutVerifying()
                ->timeout(8)
                ->connectTimeout(5)
                ->post("{$this->admissionApiUrl}/api/auth/login", $credentials);
        } catch (ConnectionException $e) {
            Log::error('Admission API unreachable', [
                'url'     => $this->admissionApiUrl,
                'message' => $e->getMessage(),
            ]);

            return [
                'success'    => false,
                'message'    => 'Could not reach the student portal. Please try again later.',
                'error_type' => 'connection_exception',
            ];
        }

        // ─── 2. Invalid credentials ────────────────────────────────────────────
        if (in_array($response->status(), [401, 422])) {
            return [
                'success'     => false,
                'unavailable' => false,
                'message'     => 'Invalid email or password.',
            ];
        }

        // ─── 3. Other API errors ───────────────────────────────────────────────
        if (!$response->successful()) {
            Log::error('Admission API error', [
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success'     => false,
                'unavailable' => true,
                'message'     => 'The student portal returned an error. Try again later.',
            ];
        }

        // ─── 4. Parse Response ─────────────────────────────────────────────────
        $data = $response->json();

        $student = $data['Student'] ?? null;

        if (!$student) {
            Log::error('Student data missing', $data);

            return [
                'success' => false,
                'message' => 'Invalid student data received.',
            ];
        }

        // ─── 5. Extract Fields ─────────────────────────────────────────────────
        $studentId = $student['student_info']['student_number']
            ?? ('EXT-' . $student['user_id']);

        $fullName = $student['full_name']
            ?? $data['user']['name']
            ?? 'Unknown';

        $email = $student['email'] ?? null;

        $status = match ($student['status'] ?? 'active') {
            'approved' => 'active',
            'blocked'  => 'blocked',
            default    => 'inactive',
        };
        $admissionToken = $data['token'] ?? null;

        // ─── 6. Sync to Local DB ───────────────────────────────────────────────
        try {
            $user = User::updateOrCreate(
                ['student_id' => $studentId],
                [
                    'full_name'     => $fullName,
                    'email'         => $email,
                    'department'    => $student['course']['department'] ?? null,
                    'status'        => $status,
                    'role_id'          => 3,
                    'password'      => null,
                    'registered_at' => now(),
                    'admission_token' => $admissionToken,
                ]
            );
        } catch (\Exception $e) {
            Log::error('DB sync failed', [
                'student_id' => $studentId,
                'message'    => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Login succeeded but failed to save user.',
            ];
        }

        // ─── 7. Generate Local Token ───────────────────────────────────────────
        $token = $user->createToken('library-token')->plainTextToken;

        Log::info('Student login success', [
            'student_id' => $studentId,
            'email'      => $email,
        ]);

        // ─── 8. Return Response ────────────────────────────────────────────────
        return [
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,

            'user' => [
                'id'            => $user->id,
                'student_id'    => $user->student_id,
                'full_name'     => $user->full_name,
                'email'         => $user->email,
                'department'    => $user->department,
                'status'        => $user->status,
                'registered_at' => $user->registered_at,
            ],

            // FULL raw API response
            'admission_response' => $data,

            // extracted parts (optional convenience)
            'external' => [
                'admission_token' => $admissionToken,
                'course_id'       => $student['course_id'] ?? null,
                'course'          => $student['course'] ?? null,
                'student_info'    => $student['student_info'] ?? null,
            ],
        ];
    }
}
