<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\BookService;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BooksController extends Controller
{
    protected $bookService;

    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }

    public function index(Request $request, $id = null)
    {
        try {
            $perPage = min($request->input('per_page', 10), 50);

            $result = $this->bookService->getBooks($id, $perPage);

            return response()->json($result);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found'
            ], 404);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400); // or 422 depending on case
        }
    }

    public function store(StoreBookRequest $request)
    {
        try {
            $book = $this->bookService->create(
                $request->validated()
            );

            return response()->json([
                'message' => 'Book created successfully.',
                'data' => $book
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function update(UpdateBookRequest $request, $id)
    {
        try {
            $book = $this->bookService->update(
                $id,
                $request->validated()
            );

            return response()->json([
                'message' => 'Book updated successfully.',
                'data' => $book
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Book not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->bookService->destroy($id);

            return response()->json([
                'message' => 'Book set status to unavailable successfully.'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Book not found'
            ], 404);
        }
    }
}
