<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id';

    protected $fillable = [
        'student_id',
        'full_name',
        'department',
        'email',
        'password',
        'role_id',
        'status',
        'registered_at',
        'last_login'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'registered_at' => 'datetime',
        'last_login' => 'datetime',
    ];
  public function role() {
    return $this->belongsTo(Role::class, 'role_id');
}


    public function isStudent(): bool
    {
        return !is_null($this->student_id);
    }


    public function isLibrarian(): bool
    {
        return $this->role->name === 'librarian';
    }


    public function isAssistant(): bool
    {
        return $this->role->name === 'assistant';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}