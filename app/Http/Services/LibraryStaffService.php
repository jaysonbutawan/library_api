<?php

namespace App\Modules\Library\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LibraryStaffService
{
    /**
     * Create staff/librarian user (stored in users table).
     */
    public function create(array $data): User
    {
        // Enforce staff roles only
        $role = $data['role'] ?? 'assistant';
        if (!in_array($role, ['librarian', 'assistant'], true)) {
            throw ValidationException::withMessages([
                'role' => 'Role must be librarian or assistant.',
            ]);
        }

        // Email normalization
        $email = strtolower(trim($data['email'] ?? ''));
        if (!$email) {
            throw ValidationException::withMessages([
                'email' => 'Email is required.',
            ]);
        }

        // Prevent creating staff with an email that already exists
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email is already taken.',
            ]);
        }

        // Password required for staff
        $plainPassword = (string) ($data['password'] ?? '');
        if ($plainPassword === '') {
            throw ValidationException::withMessages([
                'password' => 'Password is required for staff accounts.',
            ]);
        }

        $user = User::create([
            'student_id' => null, // staff always null
            'full_name' => $data['full_name'] ?? null,
            'department' => $data['department'] ?? null,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'role' => $role,
            'status' => $data['status'] ?? 'active',
            'registered_at' => now(),
            'last_login' => null,
        ]);

        return $user;
    }

    /**
     * Update staff/librarian user.
     */
    public function update(User $staff, array $data): User
    {
        // Ensure you're updating a staff account
        if (!is_null($staff->student_id) || !in_array($staff->role, ['librarian', 'assistant'], true)) {
            throw ValidationException::withMessages([
                'staff' => 'Target user is not a staff account.',
            ]);
        }

        // If changing email, normalize + ensure unique
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $email = strtolower(trim($data['email']));
            if ($email === '') {
                throw ValidationException::withMessages(['email' => 'Email cannot be empty.']);
            }

            $exists = User::where('email', $email)
                ->where('id', '!=', $staff->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['email' => 'Email is already taken.']);
            }

            $staff->email = $email;
        }

        // If changing role, validate allowed values
        if (array_key_exists('role', $data) && $data['role'] !== null) {
            if (!in_array($data['role'], ['librarian', 'assistant'], true)) {
                throw ValidationException::withMessages([
                    'role' => 'Role must be librarian or assistant.',
                ]);
            }
            $staff->role = $data['role'];
        }

        if (array_key_exists('full_name', $data)) {
            $staff->full_name = $data['full_name'];
        }

        if (array_key_exists('department', $data)) {
            $staff->department = $data['department'];
        }

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            if (!in_array($data['status'], ['active', 'blocked', 'inactive'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Status must be active, blocked, or inactive.',
                ]);
            }
            $staff->status = $data['status'];
        }

        // Optional password update
        if (!empty($data['password'])) {
            $staff->password = Hash::make((string) $data['password']);
        }

        $staff->save();

        return $staff;
    }

    /**
     * Soft-delete style: mark inactive (recommended),
     * OR hard delete if you really want.
     */
    public function delete(User $staff): void
    {
        // Recommended: do not hard delete accounts
        $staff->status = 'inactive';
        $staff->save();

        // If you want HARD delete instead:
        // $staff->delete();
    }

      public function getStaff(?int $id = null)
    {
        $query = User::whereIn('role', ['librarian', 'assistant']);

        if ($id) {
            return $query->where('id', $id)->first();
        }

        return $query->get();
    }
}