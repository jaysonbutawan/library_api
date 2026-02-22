<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\BorrowTransaction;
use App\Modules\Library\Models\Book;
use App\Modules\Library\Models\LibraryMember;
use App\Modules\Library\Requests\BorrowBookRequest;
use Illuminate\Support\Facades\DB;


class BorrowTransactionController extends Controller
{
    //Borrow a book
    public function borrow(BorrowBookRequest $request)
    {
        $validated = $request->validated();

        $member = LibraryMember::findOrFail($validated['library_member_id']);
        $book = Book::findOrFail($validated['book_id']);

        if ($member->membership_status !== 'active') {
            return response()->json([
                'message' => 'Membership is blocked.'
            ], 403);
        }

        if ($book->available_copies <= 0) {
            return response()->json([
                'message' => 'No available copies.'
            ], 400);
        }

        $transaction = DB::transaction(function () use ($member, $book) {

            $borrowDate = now();
            $dueDate = now()->addDays(7);

            $transaction = BorrowTransaction::create([
                'library_member_id' => $member->library_member_id,
                'book_id' => $book->book_id,
                'borrow_date' => $borrowDate,
                'due_date' => $dueDate,
                'status' => 'borrowed'
            ]);

            $book->decrement('available_copies');

            return $transaction;
        });

        return response()->json([
            'message' => 'Book borrowed successfully.',
            'data' => $transaction
        ], 201);
    }

    // Return a book
    public function return($transactionId)
    {
        $transaction = BorrowTransaction::with(['book', 'member'])
            ->find($transactionId);

        if (!$transaction || $transaction->status !== 'borrowed') {
            return response()->json([
                'message' => 'Transaction not found or already returned.'
            ], 404);
        }

        $today = now();
        $fine = 0;

        if ($today->gt($transaction->due_date)) {
            $daysLate = $today->diffInDays($transaction->due_date);
            $fine = $daysLate * 5;
        }

        DB::transaction(function () use ($transaction, $today, $fine) {
            $transaction->update([
                'return_date' => $today,
                'status' => 'returned',
                'fine_amount' => $fine,
            ]);

            $transaction->book->increment('available_copies');
        });

        return response()->json([
            'message' => 'Book returned successfully.',
            'fine_amount' => $fine,
            'data' => [
                'transaction_id' => $transaction->transaction_id,
                'book' => $transaction->book,
                'student_name' => $transaction->member->full_name,
                'student_department' => $transaction->member->department,
                'borrow_date' => $transaction->borrow_date,
                'due_date' => $transaction->due_date,
                'return_date' => $transaction->return_date,
                'status' => $transaction->status,
            ]
        ]);
    }

    //Get all borrow transactions for a member
    public function memberTransactions($memberId)
    {
        $transactions = BorrowTransaction::with(['book', 'member'])
            ->where('library_member_id', $memberId)
            ->get()
            ->map(function ($t) {
                return [
                    'transaction_id' => $t->transaction_id,
                    'book' => $t->book,
                    'borrow_date' => $t->borrow_date,
                    'due_date' => $t->due_date,
                    'return_date' => $t->return_date,
                    'status' => $t->status,
                    'fine_amount' => $t->fine_amount,
                    'student_name' => $t->member->full_name,
                    'student_department' => $t->member->department
                ];
            });

        return response()->json($transactions);
    }
}
