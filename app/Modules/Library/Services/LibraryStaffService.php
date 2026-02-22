<?php

namespace App\Modules\Library\Services;

use App\Modules\Library\Models\LibraryStaff;
use Illuminate\Support\Facades\Hash;

class LibraryStaffService
{
    /**
     * Login a staff member
     */
    public function login(string $email, string $password): ?LibraryStaff
    {
        $staff = LibraryStaff::where('email', $email)->first();
        if (!$staff || !Hash::check($password, $staff->password_hash) || $staff->status !== 'active') {
            return null;
        }

        // Update last login timestamp
        $staff->update(['last_login' => now()]);

        return $staff;
    }

    /**
     * Create a new staff member
     */
    public function create(array $data): LibraryStaff
    {
        $data['password_hash'] = Hash::make($data['password']);
        unset($data['password']);
        return LibraryStaff::create($data);
    }

    /**
     * Update a staff member
     */
    public function update(LibraryStaff $staff, array $data): LibraryStaff
    {
        if (!empty($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }
        $staff->update($data);
        return $staff;
    }

    /**
     * Delete a staff member
     */
    public function delete(LibraryStaff $staff): void
    {
        $staff->delete();
    }
}