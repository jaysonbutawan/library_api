<?php

namespace App\Modules\Library\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowTransaction extends Model
{
    protected $primaryKey = 'transaction_id';

    protected $fillable = [
        'library_member_id',
        'book_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        'fine_amount'
    ];

    public $timestamps = false;

    public function member()
    {
        return $this->belongsTo(LibraryMember::class, 'library_member_id');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id');
    }
}