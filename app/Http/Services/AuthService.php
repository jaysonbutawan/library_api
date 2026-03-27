<?php

namespace App\Http\Services;

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

    /**
     * Login with admission system credentials
     * Gets student data from admission API and syncs with library database
     */
    public function login(array $credentials): array
    {
        try {
            // Call admission API to validate credentials
            $response = Http::withToken($this->admissionApiToken)
                ->post("{$this->admissionApiUrl}/api/auth/login", $credentials);

            if (!$response->successful()) {
                Log::warning('Admission API login failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ];
            }

            $studentData = $response->json();

            // Validate required fields
            if (!isset($studentData['student_id'])) {
                Log::error('Missing student_id in admission API response', $studentData);

                return [
                    'success' => false,
                    'message' => 'Invalid student data received.',
                ];
            }

            // Create or update user in library system
            $user = User::updateOrCreate(
                ['student_id' => $studentData['student_id']],
                [
                    'full_name' => $studentData['full_name'] ?? $studentData['name'] ?? 'Unknown',
                    'email' => $studentData['email'] ?? null,
                    'department' => $studentData['department'] ?? null,
                    'status' => 'active',
                    'role' => 'student',
                    'password' => null,
                    'registered_at' => now(),
                ]
            );

            // Generate sanctum token for library API
            $token = $user->createToken('library-token')->plainTextToken;

            Log::info('Student login successful', [
                'student_id' => $user->student_id,
                'email' => $user->email
            ]);

            return [
                'success' => true,
                'message' => 'Login successful.',
                'user' => [
                    'id' => $user->id,
                    'student_id' => $user->student_id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'department' => $user->department,
                    'status' => $user->status,
                    'registered_at' => $user->registered_at,
                ],
                'token' => $token,
            ];
        } catch (\Exception $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred during login. Please try again.',
            ];
        }
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
