<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\BorrowTransaction;
use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Fine;

class BorrowTransactionService
{
    public function borrow(array $data)
    {
        $member = User::findOrFail($data['user_id']);

        if ($member->status !== 'active') {
            throw new \Exception('Membership is blocked.', 403);
        }

        $book = Book::where('book_id', $data['book_id'])
            ->where('status', 'available')
            ->firstOrFail();
        if ($book->available_copies <= 0) {
            throw new \Exception('No available copies.', 400);
        }

        return DB::transaction(function () use ($member, $book) {
            $transaction = BorrowTransaction::create([
                'user_id' => $member->id,
                'book_id' => $book->book_id,
                'borrow_date' => now(),
                'due_date' => now()->addDays(7),
                'status' => 'borrowed'
            ]);

            $book->decrement('available_copies');
            return $transaction;
        });
    }

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

            Book::where('book_id', $transaction->book_id)->increment('available_copies');

            return [
                'transaction_id' => $transaction->transaction_id,
                'status' => $transaction->status,
                'days_late' => $daysLate,
                'fine' => $fineCreated
            ];
        });
    }
    public function getBorrowTransactionsByMemberId($userId = null)
    {
        $query = BorrowTransaction::with(['book', 'member', 'fine']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(function ($t) {
            return [
                'transaction_id' => $t->transaction_id,
                'book' => $t->book,
                'borrow_date' => $t->borrow_date,
                'due_date' => $t->due_date,
                'return_date' => $t->return_date,
                'status' => $t->status,
                'fine' => $t->fine,
                'full_name' => $t->member?->full_name,
                'department' => $t->member?->department
            ];
        });
    }
}
