<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class StudentAuthService
{
    protected string $admissionApiUrl;
    protected string $admissionApiToken;

    public function __construct(bool $isTest = false)
    {

    if ($isTest) {
        return; // Skip API check for test login
    }
        $url   = config('services.admission.url');
        $token = config('services.admission.token');

        if (!$url || !$token) {
            throw new \RuntimeException(
                'Admission API is not configured. Check ADMISSION_API_URL and ADMISSION_API_TOKEN in .env'
            );
        }

        $this->admissionApiUrl   = $url;
        $this->admissionApiToken = $token;
    }

    public function login(array $credentials): array
    {
        // ─── 1. Network / Unreachable ────────────────────────────────────────
        try {
            $response = Http::withToken($this->admissionApiToken)
                ->timeout(8)        // stop waiting after 8 seconds
                ->connectTimeout(5) // stop connecting after 5 seconds
                ->post("{$this->admissionApiUrl}/api/auth/login", $credentials);
        } catch (ConnectionException $e) {
            // Server is down, DNS failure, refused connection, or timed out
            Log::error('Admission API unreachable', [
                'url'     => $this->admissionApiUrl,
                'message' => $e->getMessage(),
            ]);

            return [
                'success'     => false,
                'unavailable' => true,  // <-- flag the frontend can check
                'message'     => 'The student portal is currently unreachable. Please try again later or contact support.',
            ];
        }

        // ─── 2. API responded but credentials are wrong (401 / 422) ─────────
        if ($response->status() === 401 || $response->status() === 422) {
            Log::warning('Admission API rejected credentials', [
                'status' => $response->status(),
            ]);

            return [
                'success'     => false,
                'unavailable' => false,
                'message'     => 'Invalid email or password.',
            ];
        }

        // ─── 3. API responded with any other non-2xx (500, 503, etc.) ────────
        if (!$response->successful()) {
            Log::error('Admission API returned unexpected error', [
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success'     => false,
                'unavailable' => true,
                'message'     => 'The student portal returned an unexpected error. Please try again later.',
            ];
        }

        // ─── 4. Parse and validate the response payload ───────────────────────
        $studentData = $response->json();

        if (!isset($studentData['student_id'])) {
            Log::error('Missing student_id in admission API response', $studentData);

            return [
                'success'     => false,
                'unavailable' => false,
                'message'     => 'Invalid student data received from the portal.',
            ];
        }

        // ─── 5. Sync student into local DB ────────────────────────────────────
        try {
            $user = User::updateOrCreate(
                ['student_id' => $studentData['student_id']],
                [
                    'full_name'     => $studentData['full_name'] ?? $studentData['name'] ?? 'Unknown',
                    'email'         => $studentData['email'] ?? null,
                    'department'    => $studentData['department'] ?? null,
                    'status'        => 'active',
                    'role'          => 'student',
                    'password'      => null,
                    'registered_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to sync student to local DB', [
                'student_id' => $studentData['student_id'],
                'message'    => $e->getMessage(),
            ]);

            return [
                'success'     => false,
                'unavailable' => false,
                'message'     => 'Authentication succeeded but failed to create your session. Please try again.',
            ];
        }

        // ─── 6. Issue Sanctum token ───────────────────────────────────────────
        $token = $user->createToken('library-token')->plainTextToken;

        Log::info('Student login successful', [
            'student_id' => $user->student_id,
            'email'      => $user->email,
        ]);

        return [
            'success'     => true,
            'unavailable' => false,
            'message'     => 'Login successful.',
            'user'        => [
                'id'           => $user->id,
                'student_id'   => $user->student_id,
                'full_name'    => $user->full_name,
                'email'        => $user->email,
                'department'   => $user->department,
                'status'       => $user->status,
                'registered_at' => $user->registered_at,
            ],
            'token' => $token,
        ];
    }


    /**
     * Get student profile from library database
     */
    public function profile(User $user): array
    {
        return [
            'success' => true,
            'data' => [
                'id' => $user->id,
                'student_id' => $user->student_id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'department' => $user->department,
                'status' => $user->status,
                'role' => $user->role,
                'registered_at' => $user->registered_at,
                'updated_at' => $user->updated_at,
            ]
        ];
    }

    /**
     * Get student profile with library statistics
     */
    public function profileWithStats(User $user): array
    {
        $activeBorrows = $user->borrows()
            ->where('status', 'borrowed')
            ->count();

        $totalBorrows = $user->borrows()
            ->count();

        $pendingRequests = $user->requests()
            ->whereIn('status', ['pending', 'approved'])
            ->count();

        $overdueBorrows = $user->borrows()
            ->where('status', 'overdue')
            ->count();

        $unpaidFines = $user->borrows()
            ->where('fine_paid', false)
            ->whereNotNull('fine_amount')
            ->where('fine_amount', '>', 0)
            ->sum('fine_amount');

        return [
            'success' => true,
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'student_id' => $user->student_id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'status' => $user->status,
                    'role' => $user->role,
                    'registered_at' => $user->registered_at,
                ],
                'statistics' => [
                    'active_borrows' => $activeBorrows,
                    'total_borrows' => $totalBorrows,
                    'pending_requests' => $pendingRequests,
                    'overdue_count' => $overdueBorrows,
                    'unpaid_fines' => (float) $unpaidFines,
                ]
            ]
        ];
    }

    /**
     * Update user profile (basic info only)
     * Note: Student ID and role cannot be updated
     */
    public function updateProfile(User $user, array $data): array
    {
        try {
            // Only allow updating these fields
            $allowedFields = ['full_name', 'email', 'department'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (empty($updateData)) {
                return [
                    'success' => false,
                    'message' => 'No valid fields to update.',
                ];
            }

            $user->update($updateData);

            Log::info('Student profile updated', [
                'student_id' => $user->student_id,
                'updated_fields' => array_keys($updateData)
            ]);

            return [
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => $this->profile($user)['data']
            ];
        } catch (\Exception $e) {
            Log::error('Profile update error', [
                'student_id' => $user->student_id,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while updating profile.',
            ];
        }
    }

    /**
     * Logout - revoke current token
     */
    public function logout(User $user): array
    {
        try {
            // Revoke the current token
            $user->tokens()->delete();

            Log::info('Student logout successful', [
                'student_id' => $user->student_id
            ]);

            return [
                'success' => true,
                'message' => 'Logout successful.',
            ];
        } catch (\Exception $e) {
            Log::error('Logout error', [
                'student_id' => $user->student_id,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred during logout.',
            ];
        }
    }

    /**
     * Revoke all tokens (logout from all devices)
     */
    public function logoutAllDevices(User $user): array
    {
        try {
            // Revoke all tokens for this user
            $user->tokens()->delete();

            Log::info('All devices logout successful', [
                'student_id' => $user->student_id
            ]);

            return [
                'success' => true,
                'message' => 'Logged out from all devices successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Logout all devices error', [
                'student_id' => $user->student_id,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred during logout.',
            ];
        }
    }

    /**
     * Get user by student ID
     */
    public function getUserByStudentId(string $studentId): ?User
    {
        return User::where('student_id', $studentId)->first();
    }

    /**
     * Check if student is eligible for borrowing
     * (Status is active, etc.)
     */
    public function isEligibleForBorrowing(User $user): bool
    {
        return $user->status === 'active';
    }

    /**
     * Block/Unblock student account
     */
    public function updateMembershipStatus(User $user, string $status): array
    {
        $validStatuses = ['active', 'inactive', 'blocked', 'suspended'];

        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'message' => "Invalid status. Allowed: " . implode(', ', $validStatuses),
            ];
        }

        try {
            $user->update(['status' => $status]);

            Log::info('Student status updated', [
                'student_id' => $user->student_id,
                'new_status' => $status
            ]);

            return [
                'success' => true,
                'message' => "Student status updated to {$status}.",
                'data' => [
                    'student_id' => $user->student_id,
                    'status' => $user->status,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Status update error', [
                'student_id' => $user->student_id,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while updating status.',
            ];
        }
    }

}
