<?php

// app/Modules/Library/Controllers/BooksController.php
namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Library\Models\Book;
use Illuminate\Validation\Rule;

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
    public function store(Request $request)
    {
        $validated = $request->validate([
            'isbn' => 'nullable|string|unique:books,isbn',
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'total_copies' => 'required|integer|min:1',
            'available_copies' => 'nullable|integer|min:0'
        ]);

        if (!isset($validated['available_copies'])) {
            $validated['available_copies'] = $validated['total_copies'];
        }

        $book = Book::create($validated);

        return response()->json([
            'message' => 'Book created successfully',
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
    public function update(Request $request, $id)
    {
        $book = Book::find($id);
        if (!$book) return response()->json(['message' => 'Book not found'], 404);

        $validated = $request->validate([
            'isbn' => [
                'nullable',
                'string',
                Rule::unique('books')->ignore($book->book_id, 'book_id')
            ],
            'title' => 'sometimes|required|string|max:255',
            'author' => 'sometimes|nullable|string|max:255',
            'category' => 'sometimes|nullable|string|max:100',
            'total_copies' => 'sometimes|required|integer|min:1',
            'available_copies' => 'sometimes|nullable|integer|min:0'
        ]);

        // If total_copies updated and available_copies not provided, adjust available_copies proportionally
        if(isset($validated['total_copies']) && !isset($validated['available_copies'])){
            $validated['available_copies'] = $validated['total_copies'];
        }

        $book->update($validated);

        return response()->json([
            'message' => 'Book updated successfully',
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