<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;

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
    }
    public function login(array $credentials): array
{
    // ── 1. Hit the Admission API ──────────────────────────────────────────
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

    // ── 2. Handle HTTP error responses ────────────────────────────────────
    if (in_array($response->status(), [401, 422])) {
        return [
            'success'     => false,
            'unavailable' => false,
            'message'     => 'Invalid email or password.',
        ];
    }

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

    // ── 3. Parse response data ────────────────────────────────────────────
    $data      = $response->json();
    $student   = $data['Student']   ?? null;
    $applicant = $data['applicant'] ?? null;

    if (!$student) {
        Log::error('Student data missing from admission response', $data);

        return [
            'success' => false,
            'message' => 'Invalid student data received.',
        ];
    }

    // ── 4. Extract fields ─────────────────────────────────────────────────
    $studentNumber = $student['student_info']['student_number']
        ?? ('EXT-' . ($student['user_id'] ?? uniqid()));

    $fullName = $student['full_name']
        ?? trim(($applicant['first_name'] ?? '') . ' ' . ($applicant['last_name'] ?? ''))
        ?: 'Unknown';

    $email      = $student['email']           ?? $applicant['email']     ?? null;
    $department = $student['course']['department']                        ?? null;
    $courseId   = $student['course_id']       ?? $applicant['course_id'] ?? null;

    $status = match ($student['status'] ?? 'inactive') {
        'approved' => 'active',
        'blocked'  => 'blocked',
        default    => 'inactive',
    };

    $admissionToken = $data['token'] ?? null;

    // ── 5. Sync user to local DB ──────────────────────────────────────────
    try {
        $user = User::where('student_id', $studentNumber)
                    ->orWhere('email', $email)
                    ->first();

        if ($user) {
            $changes = [];

            if ($user->student_id  !== $studentNumber) $changes['student_id']  = $studentNumber;
            if ($user->full_name   !== $fullName)      $changes['full_name']   = $fullName;
            if ($user->email       !== $email)         $changes['email']       = $email;
            if ($user->department  !== $department)    $changes['department']  = $department;
            if ($user->status      !== $status)        $changes['status']      = $status;

            $changes['last_login'] = now();

            $user->update($changes);

        } else {
            $user = User::create([
                'student_id'    => $studentNumber,
                'full_name'     => $fullName,
                'email'         => $email,
                'department'    => $department,
                'status'        => $status,
                'role_id'       => 3,
                'password'      => null,
                'registered_at' => now(),
                'last_login'    => now(),
            ]);
        }

    } catch (\Exception $e) {
        Log::error('DB sync failed', [
            'student_id' => $studentNumber,
            'email'      => $email,
            'message'    => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'message' => 'Login succeeded but failed to save user.',
            'debug'   => $e->getMessage(), // remove in production
        ];
    }

    // ── 6. Issue local API token ──────────────────────────────────────────
    $token = $user->createToken('library-token')->plainTextToken;

    Log::info('Student login success', [
        'student_id' => $studentNumber,
        'email'      => $email,
    ]);

    // ── 7. Return response ────────────────────────────────────────────────
    return [
        'success' => true,
        'message' => 'Login successful.',
        'token'   => $token,
        'user'    => [
            'id'            => $user->id,
            'student_id'    => $user->student_id,
            'full_name'     => $user->full_name,
            'email'         => $user->email,
            'department'    => $user->department,
            'status'        => $user->status,
            'registered_at' => $user->registered_at,
            'role'          => [
                'name' => $user->role->name,
            ],
        ],
        'external' => [
            'admission_token' => $admissionToken,
            'course_id'       => $courseId,
            'course'          => $student['course']       ?? null,
            'student_info'    => $student['student_info'] ?? null,
        ],
    ];
}
    public function logout(): array
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not authenticated.',
                ];
            }

            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token = $user->currentAccessToken();

            // The editor will now see the 'delete' method on $token
            if ($token) {
                $token->delete();
            }
            return [
                'success' => true,
                'message' => 'Logged out successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Logout failed.',
            ];
        }
    }
}
