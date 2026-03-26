<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $primaryKey = 'category_id';

    protected $fillable = ['name'];

    public $timestamps = false;

    public function books()
    {
        return $this->hasMany(Book::class, 'category_id', 'category_id');
    }
}
