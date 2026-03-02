<?php

namespace App\Modules\Library\Services;

use App\Modules\Library\Models\BorrowTransaction;
use App\Modules\Library\Models\Book;
use App\Modules\Library\Models\LibraryMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Modules\Library\Models\Fine;

class BorrowTransactionService
{
    //Borrow Book

    public function borrow(array $data)
    {
        $member = LibraryMember::find($data['library_member_id']);
        if (!$member) {
            throw new ModelNotFoundException('Member not found.');
        }

        if ($member->membership_status !== 'active') {
            throw new \Exception('Membership is blocked.', 403);
        }

        $book = Book::where('book_id', $data['book_id'])->where('status', 'available')->first();
        if (!$book) {
            throw new ModelNotFoundException('Book not found.');
        }

        if ($book->available_copies <= 0) {
            throw new \Exception('No available copies.', 400);
        }

        return DB::transaction(function () use ($member, $book) {

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
    }

    //Return Book

    public function returnBook($transactionId)
    {
        $transaction = BorrowTransaction::with(['book', 'member'])
            ->find($transactionId);

        if (!$transaction) {
            throw new ModelNotFoundException('Transaction not found.');
        }

        if ($transaction->status !== 'borrowed') {
            throw new \Exception('Book already returned.', 400);
        }

        $today = now();

        return DB::transaction(function () use ($transaction, $today) {

            $daysLate = 0;
            $fineCreated = null;

            if ($today->gt($transaction->due_date)) {

                $daysLate = $today->diffInDays($transaction->due_date);

                $rate = 0;
                $amount = $daysLate * $rate;

                $fineCreated = Fine::create([
                    'transaction_id' => $transaction->transaction_id,
                    'days_late' => $daysLate,
                    'rate_per_day' => $rate,
                    'amount' => $amount,
                    'paid_status' => 'unpaid'
                ]);

                $transaction->update([
                    'status' => 'overdue',
                    'return_date' => $today
                ]);
            } else {

                $transaction->update([
                    'status' => 'returned',
                    'return_date' => $today
                ]);
            }

            $transaction->book->increment('available_copies');

            return [
                'transaction_id' => $transaction->transaction_id,
                'status' => $transaction->status,
                'days_late' => $daysLate,
                'fine' => $fineCreated
            ];
        });
    }

    // Member Transactions

    public function getBorrowTransactionsByMemberId($memberId)
    {
        return BorrowTransaction::with(['book', 'member'])
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
    }
}
