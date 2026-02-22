<?php

namespace App\Modules\Library\Services;

use App\Modules\Library\Models\Book;
use Faker\Calculator\Isbn;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookService
{
 
    public function getAll()
    {
        return Book::all();
    }

    public function create(array $data)
    {
        if (!empty($data['isbn']) && !Isbn::isValid($data['isbn'])) {
            throw new \Exception('Invalid ISBN format.', 422);
        }

        $data['available_copies'] =
            $data['available_copies'] ?? $data['total_copies'];

        return Book::create($data);
    }

    public function findById($id)
    {
        $book = Book::find($id);

        if (!$book) {
            throw new ModelNotFoundException('Book not found.');
        }

        return $book;
    }

    public function update($id, array $data)
    {
        $book = $this->findById($id);

        if (!empty($data['isbn']) && !Isbn::isValid($data['isbn'])) {
            throw new \Exception('Invalid ISBN format.', 422);
        }

        if (isset($data['total_copies']) && !isset($data['available_copies'])) {
            $data['available_copies'] = $data['total_copies'];
        }

        if (
            isset($data['available_copies']) &&
            isset($data['total_copies']) &&
            $data['available_copies'] > $data['total_copies']
        ) {
            throw new \Exception(
                'Available copies cannot exceed total copies.',
                422
            );
        }

        $book->update($data);

        return $book;
    }


    public function delete($id)
    {
        $book = $this->findById($id);
        $book->delete();

        return true;
    }
}