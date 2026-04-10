<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $table = 'fines';

    protected $primaryKey = 'fine_id';

    protected $fillable = [
        'transaction_id',
        'user_id',
        'amount',
        'last_paid_at',
        'is_fully_paid',
        'created_at',
    ];

    public $timestamps = false;

    public function transaction()
    {
        return $this->belongsTo(BorrowTransaction::class, 'transaction_id', 'transaction_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
