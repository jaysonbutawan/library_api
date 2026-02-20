<?php

namespace App\Modules\Library\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $primaryKey = 'fine_id';
    protected $fillable = ['transaction_id', 'amount', 'paid_status'];
    public $timestamps = false;

    /**
     * Borrow transaction related to this fine
     */
    public function transaction()
    {
        return $this->belongsTo(BorrowTransaction::class, 'transaction_id');
    }

    /**
     * Get student info from the library member of this transaction
     */
    public function getStudentInfo(): array
    {
        $member = $this->transaction->member ?? null;

        if (!$member) {
            return [
                'full_name' => null,
                'department' => null,
                'email' => null,
                'membership_status' => null,
            ];
        }

        return [
            'full_name' => $member->full_name,
            'department' => $member->department,
            'email' => $member->email,
            'membership_status' => $member->membership_status,
        ];
    }
}