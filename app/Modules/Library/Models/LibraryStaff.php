<?php

namespace App\Modules\Library\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class LibraryStaff extends Authenticatable
{
    use HasFactory, HasApiTokens;

    protected $table = 'library_staff';

    protected $primaryKey = 'staff_id';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'role',
        'status',
        'last_login'
    ];

    protected $hidden = [
        'password_hash', // hide password hash in JSON responses
    ];

    protected $casts = [
        'last_login' => 'datetime',
    ];

    /**
     * Accessor for Laravel authentication
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Check if staff is a librarian
     */
    public function isLibrarian(): bool
    {
        return $this->role === 'librarian';
    }

    /**
     * Check if staff is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}