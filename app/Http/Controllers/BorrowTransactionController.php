<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\BorrowTransactionService;
use App\Http\Requests\BorrowBookRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BorrowTransactionController extends Controller
{
    protected $service;

    public function __construct(BorrowTransactionService $service)
    {
        $this->service = $service;
    }

    public function borrow(BorrowBookRequest $request)
    {
        try {
            $transaction = $this->service->borrow(
                $request->validated()
            );

            return response()->json([
                'message' => 'Book borrowed successfully.',
                'data' => $transaction
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            $status = ($code >= 400 && $code < 600) ? $code : 500;

            return response()->json([
                'message' => $e->getMessage()
            ], $status);
        }
    }

    public function returnBook($transactionId)
    {
        try {
            $result = $this->service->returnBook($transactionId);

            return response()->json([
                'message' => 'Book returned successfully.',
                'data' => $result
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            $status = ($code >= 400 && $code < 600) ? $code : 500;

            return response()->json([
                'message' => $e->getMessage()
            ], $status);
        }
    }

    public function getBorrowTransactionsByMemberId(Request $request, $userId = null)
    {
        return response()->json(
            $this->service->getBorrowTransactionsByMemberId($userId)
        );
    }
}
