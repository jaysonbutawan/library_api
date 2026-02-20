<?php

namespace App\Modules\Library\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryMember extends Model
{
    protected $table = 'library_members';
    protected $primaryKey = 'library_member_id';

    protected $fillable = [
        'student_id',
        'membership_status',
        'registered_at',
        'full_name',
        'department',
        'email'
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public $timestamps = false;
}