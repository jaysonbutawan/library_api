<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Library\Models\Fine;
use App\Modules\Library\Models\BorrowTransaction;

class FinesController extends Controller
{
    /**
     * Create a fine for a borrow transaction
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:borrow_transactions,transaction_id',
            'amount' => 'required|numeric|min:0'
        ]);

        $fine = Fine::create([
            'transaction_id' => $validated['transaction_id'],
            'amount' => $validated['amount'],
            'paid_status' => 'unpaid'
        ]);

        return response()->json([
            'message' => 'Fine created successfully',
            'data' => $fine
        ], 201);
    }

    /**
     * Pay a fine
     */
    public function pay(Request $request, $fineId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0'
        ]);

        $fine = Fine::find($fineId);
        if (!$fine) {
            return response()->json(['message' => 'Fine not found'], 404);
        }

        // Optional: Check that amount matches fine
        if ($validated['amount'] < $fine->amount) {
            return response()->json(['message' => 'Amount is less than fine'], 400);
        }

        $fine->update([
            'paid_status' => 'paid'
        ]);

        return response()->json([
            'message' => 'Fine payment successful',
            'data' => $fine
        ]);
    }

    /**
     * Get all fines for a library member
     */
    public function memberFines($memberId)
    {
        $fines = Fine::whereHas('transaction', function ($q) use ($memberId) {
            $q->where('library_member_id', $memberId);
        })->with(['transaction.member.student'])->get(); // eager load student

        // Format response with student name and department
        $result = $fines->map(function ($fine) {
            $student = $fine->transaction->member->student ?? null;
            return [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'paid_status' => $fine->paid_status,
                'transaction_id' => $fine->transaction_id,
                'student_name' => $student->full_name ?? null,
                'student_department' => $student->department->name ?? null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get unpaid fines for cashier
     */
   public function unpaidFines()
{
    $fines = Fine::where('paid_status', 'unpaid')->with(['transaction.member.student'])->get();

    $result = $fines->map(function($fine) {
        $student = $fine->transaction->member->student ?? null;
        return [
            'fine_id' => $fine->fine_id,
            'amount' => $fine->amount,
            'transaction_id' => $fine->transaction_id,
            'student_name' => $student->full_name ?? null,
            'student_department' => $student->department->name ?? null,
        ];
    });

    return response()->json($result);
}
}
