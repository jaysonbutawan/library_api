<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Book;

class BorrowTransaction extends Model
{
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'id',
        'book_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        ];

    public $timestamps = false;

    public function member()
    {
        return $this->belongsTo(User::class, 'id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function fine()
    {
        return $this->hasOne(Fine::class, 'transaction_id', 'transaction_id');
    }
}