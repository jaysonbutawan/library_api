<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Services\BookService;
use App\Modules\Library\Requests\StoreBookRequest;
use App\Modules\Library\Requests\UpdateBookRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BooksController extends Controller
{
    protected $bookService;

    public function __construct(BookService $bookService)
    {
        $this->bookService = $bookService;
    }

    public function index()
    {
        return response()->json(
            $this->bookService->getAll()
        );
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

    public function show($id)
    {
        try {
            $book = $this->bookService->findById($id);
            if($book->status !== 'available') {
                return response()->json(['message' => 'Book is currently unavailable'], 404);
            }
            return response()->json($book);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Book not found'
            ], 404);
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