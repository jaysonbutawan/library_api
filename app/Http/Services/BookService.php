<?php

namespace App\Http\Services;

use App\Models\Book;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class BookService
{
    public function create(array $data): Book
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

    public function getBooks($id = null, int $perPage = 10)
    {
        $query = Book::with('category');

        // 🔍 SEARCH
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
            });
        }

        //  CATEGORY
        if ($category = request('category')) {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', $category);
            });
        }

        // STATUS
        if ($status = request('status')) {
            $query->where('status', $status);
        }

        $query->orderBy('book_id');

        // =========================
        // SINGLE BOOK → RETURN MODEL
        // =========================
        if ($id !== null) {
            $book = $query->where('book_id', $id)->firstOrFail();

            if ($book->status !== 'available') {
                throw new \DomainException('Book is currently unavailable');
            }

            return $book;
        }

        // =========================
        // MULTIPLE → RETURN PAGINATOR (MODELS)
        // =========================
        return $query->cursorPaginate($perPage);
    }
    public function update($id, array $data): Book
    {
        // Use the dedicated method to avoid request filters
        $book = $this->getBookById($id);

        // Update the fields passed in $data
        $book->update($data);

        return $book;
    }

    public function getBookById($id): Book
    {
        // Fetch a book directly by its ID, no filters applied
        return Book::findOrFail($id);
    }

    public function destroy($id): Book
    {
        $book = Book::find($id);

        if (!$book) {
            throw new ModelNotFoundException('Book not found.');
        }

        $book->update(['status' => 'unavailable']);

        return $book;
    }
}
