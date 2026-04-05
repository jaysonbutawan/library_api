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
    public function returnBook($transactionId)
    {
        try {
            $result = $this->service->returnBook($transactionId);

            return response()->json([
                'message' => $result['message'],
                'data' => [
                    'transaction_id' => $result['transaction_id'],
                    'status' => $result['status'],
                    'return_date' => $result['return_date'],
                    'days_overdue' => $result['days_overdue'],
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
