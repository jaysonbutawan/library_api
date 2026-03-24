<?php

namespace App\Http\Services;

use App\Models\Book;
use Faker\Calculator\Isbn;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class BookService
{

    public function create(array $data)
    {
        if (empty($data['isbn'])) {
            throw ValidationException::withMessages(['isbn' => ['Isbn is required.']]);
        }

        $data['available_copies'] =
            $data['available_copies'] ?? $data['total_copies'];
        if ($data['available_copies'] > $data['total_copies']) {
            throw ValidationException::withMessages([
                'available_copies' => ['available_copies cannot be greater than total_copies.']
            ]);
        }

        return Book::create($data);
    }

    public function getBooks($id = null)
    {
        if ($id === null) {
            return Book::all();
        }

        return Book::findOrFail($id);
    }


    public function update($id, array $data)
    {
        $book = $this->getBooks($id);

        if (!empty($data['isbn']) && !Isbn::isValid($data['isbn'])) {
            throw ValidationException::withMessages(['isbn' => ['Invalid ISBN format.']]);
        }

        if (isset($data['total_copies']) && !isset($data['available_copies'])) {
            $data['available_copies'] = $data['total_copies'];
        }

        if (
            isset($data['available_copies']) &&
            isset($data['total_copies']) &&
            $data['available_copies'] > $data['total_copies']
        ) {
            throw ValidationException::withMessages([
                'available_copies' => ['Available copies cannot exceed total copies.']
            ]);
        }

        $book->update($data);

        return $book;
    }

    public function destroy($id)
    {
        $book = Book::find($id);

        if (!$book) {
            throw new ModelNotFoundException('Book not found.');
        }

        $book->update(['status' => 'unavailable']);

        return $book;
    }
}
