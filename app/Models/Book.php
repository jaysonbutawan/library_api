<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'book_id';

    protected $fillable = [
        'isbn',
        'title',
        'author',
        'status',
        'category_id',
        'total_copies',
        'publication_year',
        'available_copies'
    ];

    public $timestamps = false;

     public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }
}
