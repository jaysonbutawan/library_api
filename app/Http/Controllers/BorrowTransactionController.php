<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\BorrowTransactionService;
use App\Http\Requests\BorrowBookRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function return($transactionId)
    {
        try {
            $result = $this->service->returnBook($transactionId);

            return response()->json([
                'message' => 'Book returned successfully.',
                'fine_amount' => $result['fine'],
                'data' => $result['transaction']
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function getBorrowTransactionsByMemberId($memberId)
    {
        return response()->json(
            $this->service->getBorrowTransactionsByMemberId($memberId)
        );
    }
}
