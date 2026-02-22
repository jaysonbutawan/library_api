<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\Book;
use Faker\Calculator\Isbn;
use App\Modules\Library\Requests\StoreBookRequest;
use App\Modules\Library\Requests\UpdateBookRequest;

class BooksController extends Controller
{
    /**
     * List all books
     */
    public function index()
    {
        $books = Book::all();
        return response()->json($books);
    }

    /**
     * Create a new book
     */
    public function store(StoreBookRequest $request)
    {
        $validated = $request->validated();

        if (!empty($validated['isbn']) && !Isbn::isValid($validated['isbn'])) {
            return response()->json(['message' => 'Invalid ISBN format.'], 422);
        }

        $validated['available_copies'] =
            $validated['available_copies'] ?? $validated['total_copies'];

        $book = Book::create($validated);

        return response()->json([
            'message' => 'Book created successfully.',
            'data' => $book
        ], 201);
    }

    /**
     * Show a single book
     */
    public function show($id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }
        return response()->json($book);
    }

    /**
     * Update a book
     */
    public function update(UpdateBookRequest $request, $id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.'
            ], 404);
        }

        $validated = $request->validated();

        if (!empty($validated['isbn']) && !Isbn::isValid($validated['isbn'])) {
            return response()->json([
                'message' => 'Invalid ISBN format.'
            ], 422);
        }
        if (isset($validated['total_copies']) && !isset($validated['available_copies'])) {
            $validated['available_copies'] = $validated['total_copies'];
        }

        if (
            isset($validated['available_copies']) &&
            isset($validated['total_copies']) &&
            $validated['available_copies'] > $validated['total_copies']
        ) {
            return response()->json([
                'message' => 'Available copies cannot exceed total copies.'
            ], 422);
        }

        $book->update($validated);

        return response()->json([
            'message' => 'Book updated successfully.',
            'data' => $book
        ]);
    }

    /**
     * Delete a book
     */
    public function destroy($id)
    {
        $book = Book::find($id);
        if (!$book) return response()->json(['message' => 'Book not found'], 404);

        $book->delete();
        return response()->json(['message' => 'Book deleted successfully']);
    }
}
