<?php

namespace App\Http\Services;

use App\Models\Book;
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
        return Book::with('category')->get();
    }
    return Book::with('category')->findOrFail($id);
}


    public function update($id, array $data)
    {
        $book = $this->getBooks($id);
        if (!empty($data['isbn']) && $data['isbn'] !== $book->isbn) {
            if (Book::where('isbn', $data['isbn'])->exists()) {
                throw ValidationException::withMessages(['isbn' => ['This ISBN already exists.']]);
            }
        }

        if (isset($data['total_copies']) && isset($data['available_copies'])) {
            if ($data['available_copies'] > $data['total_copies']) {
                throw ValidationException::withMessages([
                    'available_copies' => ['Available copies cannot exceed total copies.']
                ]);
            }
        } elseif (isset($data['total_copies']) && !isset($data['available_copies'])) {
            if ($book->available_copies > $data['total_copies']) {
                throw ValidationException::withMessages([
                    'available_copies' => ['Available copies (' . $book->available_copies . ') cannot exceed new total copies (' . $data['total_copies'] . ').']
                ]);
            }
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
