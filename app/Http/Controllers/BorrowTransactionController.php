<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\BorrowTransactionService;
use App\Http\Requests\BorrowBookRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class BorrowTransactionController extends Controller
{
    protected $service;

    public function __construct(BorrowTransactionService $service)
    {
        $this->service = $service;
    }

    /**
     * Step 1: Student requests a book
     * POST /api/borrow-requests
     */
    public function requestBook(BorrowBookRequest $request)
    {
        try {
            $result = $this->service->requestBook($request->validated());

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'request_id' => $result['request_id'],
                    'queue_position' => $result['queue_position'],
                    'book_title' => $result['book_title'],
                ]
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            $status = ($code >= 400 && $code < 600) ? $code : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Step 2: Librarian approves a request
     * PATCH /api/borrow-requests/{requestId}/approve
     */
    public function approveRequest($requestId)
    {
        try {
            $result = $this->service->approveRequest($requestId);

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'request_id' => $result['request_id'],
                    'status' => $result['status'],
                    'expires_at' => $result['expires_at'],
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function rejectRequest($requestId)
    {
        try {
            $result = $this->service->rejectRequest($requestId);

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'request_id' => $result['request_id'],
                    'status' => $result['status'],
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }



    /**
     * Step 3: Librarian completes the borrow (student picks up)
     * POST /api/borrow-requests/{requestId}/complete
     */
    public function completeBorrow($requestId)
    {
        try {
            $dueDateDays = request()->input('due_days');

            if (!$dueDateDays || !is_numeric($dueDateDays) || $dueDateDays < 1 || $dueDateDays > 30) {
                throw ValidationException::withMessages([
                    'due_days' => ['Due date must be a number between 1 and 30.']
                ]);
            }
            $result = $this->service->completeBorrow($requestId, $dueDateDays);

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'transaction_id' => $result['transaction_id'],
                    'request_id' => $result['request_id'],
                    'borrow_date' => $result['borrow_date'],
                    'due_date' => $result['due_date'],
                ]
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return a borrowed book
     * POST /api/borrow-transactions/{transactionId}/return
     */
    public function returnBook(Request $request, $id)
    {
        // Validate here (NOT in service)
        $validated = $request->validate([
            'fine_per_day' => 'nullable|numeric|min:0',
            'fine_amount'  => 'nullable|numeric|min:0',
        ]);

        $result = $this->service->returnBook($id, $validated);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $result['requires_payment']
                ? "Book returned with fine."
                : "Book returned successfully."
        ]);
    }

    /**
     * Cancel a borrow request
     * DELETE /api/borrow-requests/{requestId}
     */
    public function cancelRequest($requestId)
    {
        try {
            $result = $this->service->cancelRequest($requestId);

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'request_id' => $result['request_id'],
                    'status' => $result['status'],
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get queue status for a specific book
     * GET /api/books/{bookId}/queue
     */
    public function getBookQueue($bookId)
    {
        try {
            $queueStatus = $this->service->getBookQueue($bookId);

            return response()->json([
                'message' => 'Queue status retrieved.',
                'data' => $queueStatus
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all borrow requests for a user
     * GET /api/users/{userId}/requests
     */
    public function getUserRequests(Request $request, $userId = null)
    {
        try {
            $perPage = min($request->input('per_page', 10), 50);

            $requests = $this->service->getUserRequests($userId, $perPage);

            return response()->json([
                'message' => $userId
                    ? 'User requests retrieved.'
                    : 'All requests retrieved.',

                // ✅ FLATTEN RESPONSE (VERY IMPORTANT)
                'data' => $requests['data'],
                'meta' => $requests['meta'],

                // ✅ Correct count
                'count' => count($requests['data']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get borrow transactions (with optional user filter)
     * GET /api/borrow-transactions?user_id={userId}
     */
    public function getBorrowTransactions(Request $request)
    {
        try {
            $userId = $request->query('user_id');
            $transactions = $this->service->getBorrowTransactionsByMemberId($userId);

            return response()->json([
                'message' => 'Borrow transactions retrieved.',
                'data' => $transactions
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    /**
     * Get all transactions associated with a specific book
     * Route: GET /api/library/books/{bookId}/transactions
     */
    public function getTransactionsByBook(Request $request, $bookId)
    {
        try {
            // 1. Sanitize the per_page input (default 10, max 50)
            $perPage = (int) $request->input('per_page', 10);
            $perPage = ($perPage > 0 && $perPage <= 50) ? $perPage : 10;

            // 2. Call the service method
            $transactions = $this->service->getTransactionsByBookId((int) $bookId, $perPage);

            // 3. Return the flattened JSON response
            return response()->json([
                'message' => "Transactions for Book ID: {$bookId} retrieved successfully.",

                // ✅ Flattened data as per your reference
                'data'  => $transactions['data'],
                'meta'  => $transactions['meta'],

                // ✅ Total count of items in the current cursor page
                'count' => count($transactions['data']),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'The specified book was not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching transactions.',
                'error'   => $e->getMessage() // Useful for debugging (remove in production)
            ], 500);
        }
    }

    /**
     * Get borrow transactions for a specific user
     * GET /api/users/{userId}/transactions
     */
    public function getUserTransactions(Request $request, $userId)
    {
        try {
            $perPage = min($request->input('per_page', 10), 50);
            $transactions = $this->service->getBorrowTransactionsByMemberId($userId, $perPage);

            return response()->json([
                'message' => 'User transactions retrieved.',
                'data' => $transactions
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pay fine for overdue book
     * POST /api/borrow-transactions/{transactionId}/pay-fine
     */
    public function payFine($transactionId)
    {
        try {
            $result = $this->service->payFine($transactionId);

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'transaction_id' => $result['transaction_id'],
                    'fine_amount' => $result['fine_amount'],
                    'fine_paid' => $result['fine_paid'],
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
