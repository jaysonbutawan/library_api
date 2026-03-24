<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $table = 'fines';

    protected $primaryKey = 'fine_id';

    protected $fillable = [
        'transaction_id',
        'amount',
        'paid_status',
        'days_late',
        'rate_per_day'
    ];

    public $timestamps = false;

    public function transaction()
    {
        return $this->belongsTo(BorrowTransaction::class, 'transaction_id', 'transaction_id');
    }
}