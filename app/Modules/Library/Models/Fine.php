<?php

namespace App\Modules\Library\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $primaryKey = 'fine_id';
    protected $fillable = ['transaction_id', 'amount', 'paid_status'];
    public $timestamps = false;

    public function transaction()
    {
        return $this->belongsTo(BorrowTransaction::class, 'transaction_id');
    }

    // Shortcut to get student via transaction -> member -> user
    public function student()
    {
        return $this->transaction->member->student ?? null;
    }
}
