<?php
// app/Modules/Library/Models/Book.php
namespace App\Modules\Library\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'book_id';

    protected $fillable = [
        'isbn',
        'title',
        'author',
        'status',
        'category',
        'total_copies',
        'publication_year',
        'available_copies'
    ];

    public $timestamps = false;
}
