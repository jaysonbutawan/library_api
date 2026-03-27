<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\BorrowTransaction;
use App\Models\BorrowRequest;
use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BorrowTransactionService
{
    /**
     * Step 1: Student requests a book (adds to queue)
     * Returns queue position
     */
    public function requestBook(array $data)
    {
        $member = User::findOrFail($data['user_id']);

        if ($member->status !== 'active') {
            throw new \Exception('Membership is blocked.', 403);
        }

        $book = Book::findOrFail($data['book_id']);

        // Check if user already has a pending request for this book
        $existingRequest = BorrowRequest::where('user_id', $member->id)
            ->where('book_id', $book->book_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            throw ValidationException::withMessages([
                'book_id' => ['You already have a pending request for this book.']
            ]);
        }

        // Check if user already borrowed this book and hasn't returned it
        $activeBorrow = BorrowTransaction::where('user_id', $member->id)
            ->where('book_id', $book->book_id)
            ->where('status', 'borrowed')
            ->first();

        if ($activeBorrow) {
            throw ValidationException::withMessages([
                'book_id' => ['You already have an active borrow of this book. Please return it first.']
            ]);
        }

        return DB::transaction(function () use ($member, $book) {
            // Get next queue position for this book
            $lastPosition = BorrowRequest::where('book_id', $book->book_id)
                ->where('status', 'pending')
                ->max('queue_position') ?? 0;

            $request = BorrowRequest::create([
                'user_id' => $member->id,
                'book_id' => $book->book_id,
                'queue_position' => $lastPosition + 1,
                'status' => 'pending',
                'requested_at' => Carbon::now(),
            ]);

            return [
                'request_id' => $request->request_id,
                'queue_position' => $request->queue_position,
                'message' => "Request submitted. You are #{$request->queue_position} in queue.",
                'book_title' => $book->title,
            ];
        });
    }

    /**
     * Step 2: Librarian approves the request (student is next to pick up)
     * This means the book is ready for pickup
     */
    public function approveRequest($requestId)
    {
        $request = BorrowRequest::findOrFail($requestId);

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ["Request is already {$request->status}"]
            ]);
        }

        $book = Book::findOrFail($request->book_id);

        // Check if book has available copies
        if ($book->available_copies <= 0) {
            throw ValidationException::withMessages([
                'available_copies' => ['No copies available at this moment.']
            ]);
        }

        // Check if this request is next in queue FOR THIS SPECIFIC BOOK
        $nextInQueue = BorrowRequest::where('book_id', $request->book_id)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->first();

        if (!$nextInQueue) {
            throw ValidationException::withMessages([
                'queue' => ["No pending requests found for this book."]
            ]);
        }

        // ✅ FIX: Cast both to int for comparison
        if ((int)$nextInQueue->request_id !== (int)$requestId) {
            $position = $request->queue_position;
            throw ValidationException::withMessages([
                'queue' => ["Cannot approve. Request #{$position} is not first in queue for this book."]
            ]);
        }

        return DB::transaction(function () use ($request) {
            $request->approve();

            return [
                'request_id' => $request->request_id,
                'status' => 'approved',
                'expires_at' => $request->expires_at,
                'message' => 'Request approved! Student has 7 days to pick up the book.',
            ];
        });
    }

    /**
     * Step 3: Student picks up the book (librarian completes the borrow)
     * This creates the transaction and decrements available_copies
     */
    public function completeBorrow($requestId, $dueDateDays = 7)
    {
        $request = BorrowRequest::with('book')->findOrFail($requestId);

        if ($request->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Request must be approved before completing borrow.']
            ]);
        }

        $book = $request->book;

        // Check again if book has available copies
        if ($book->available_copies <= 0) {
            throw ValidationException::withMessages([
                'available_copies' => ['No copies available. Cannot complete borrow.']
            ]);
        }

        return DB::transaction(function () use ($request, $book, $dueDateDays) {
            // Create the actual borrow transaction
            $transaction = BorrowTransaction::create([
                'user_id' => $request->user_id,
                'book_id' => $request->book_id,
                'request_id' => $request->request_id,
                'borrow_date' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays($dueDateDays)->toDateString(),
                'status' => 'borrowed',
            ]);

            // DECREMENT available_copies
            $book->decrement('available_copies');

            // Auto-promote next in queue if there is one
            $this->promoteNextInQueue($book->book_id);

            return [
                'transaction_id' => $transaction->transaction_id,
                'request_id' => $request->request_id,
                'user_id' => $request->user_id,
                'book_id' => $book->book_id,
                'borrow_date' => $transaction->borrow_date,
                'due_date' => $transaction->due_date,
                'message' => 'Book borrowed successfully!',
            ];
        });
    }

    /**
     * Student returns the book
     */
    public function returnBook($transactionId)
    {
        $transaction = BorrowTransaction::with(['book', 'user', 'request'])
            ->findOrFail($transactionId);

        if ($transaction->status !== 'borrowed') {
            throw ValidationException::withMessages([
                'status' => ['This book has already been returned.']
            ]);
        }

        $returnDate = Carbon::now();
        $daysOverdue = $returnDate->diffInDays($transaction->due_date, false);
        $isOverdue = $daysOverdue > 0;

        return DB::transaction(function () use ($transaction, $returnDate, $daysOverdue, $isOverdue) {
            // Update transaction
            $transaction->update([
                'return_date' => $returnDate->toDateString(),
                'status' => $isOverdue ? 'overdue' : 'returned',
                'days_overdue' => max(0, $daysOverdue),
                'fine_amount' => $isOverdue ? $daysOverdue * 10 : null, // 10 per day
            ]);

            // INCREMENT available_copies back
            $book = Book::findOrFail($transaction->book_id);
            $book->increment('available_copies');

            // Auto-promote next in queue
            $this->promoteNextInQueue($transaction->book_id);

            return [
                'transaction_id' => $transaction->transaction_id,
                'status' => $transaction->status,
                'return_date' => $transaction->return_date,
                'days_overdue' => $transaction->days_overdue,
                'fine_amount' => $transaction->fine_amount,
                'fine_paid' => $transaction->fine_paid,
                'message' => $isOverdue
                    ? "Book returned late. Fine: ₱{$transaction->fine_amount}"
                    : 'Book returned on time. Thank you!',
            ];
        });
    }

    /**
     * Cancel a request (student changes mind or request expires)
     */
    public function cancelRequest($requestId)
    {
        $request = BorrowRequest::findOrFail($requestId);

        if ($request->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Request is already cancelled.']
            ]);
        }

        return DB::transaction(function () use ($request) {
            // If approved but not picked up within 7 days, auto-expire
            if ($request->status === 'approved' && Carbon::now()->greaterThan($request->expires_at)) {
                $request->expire();
            } else {
                $request->cancel();
            }

            // Promote next in queue
            $this->promoteNextInQueue($request->book_id);

            return [
                'request_id' => $request->request_id,
                'status' => $request->status,
                'message' => 'Request cancelled.',
            ];
        });
    }

    /**
     * Helper: When a book is returned or request is cancelled,
     * automatically promote next person in queue
     */
    private function promoteNextInQueue($bookId)
    {
        $nextRequest = BorrowRequest::where('book_id', $bookId)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->first();

        if ($nextRequest) {
            $nextRequest->approve();

            // TODO: Send notification to student (email, SMS, in-app)
            // Example: Notification::send($nextRequest->user, new BookReadyNotification($nextRequest));
        }
    }

    /**
     * Get queue status for a book (admin view)
     */
    public function getBookQueue($bookId)
    {
        $book = Book::findOrFail($bookId);

        $pendingQueue = BorrowRequest::where('book_id', $bookId)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->with('user')
            ->get()
            ->map(function ($request) {
                return [
                    'request_id' => $request->request_id,
                    'user_name' => $request->user->name ?? $request->user->full_name,
                    'user_id' => $request->user_id,
                    'queue_position' => $request->queue_position,
                    'requested_at' => $request->requested_at,
                    'status' => $request->status,
                ];
            });

        $approved = BorrowRequest::where('book_id', $bookId)
            ->where('status', 'approved')
            ->with('user')
            ->first();

        return [
            'book_id' => $book->book_id,
            'book_title' => $book->title,
            'available_copies' => $book->available_copies,
            'total_copies' => $book->total_copies,
            'approved_for' => $approved ? [
                'request_id' => $approved->request_id,
                'user_name' => $approved->user->name ?? $approved->user->full_name,
                'user_id' => $approved->user_id,
                'expires_at' => $approved->expires_at,
                'days_remaining' => $approved->daysUntilExpiry(),
            ] : null,
            'pending_queue' => $pendingQueue,
            'queue_count' => $pendingQueue->count(),
        ];
    }

    /**
     * Get borrow transactions with optional user filter
     */
    public function getBorrowTransactionsByMemberId($userId = null)
    {
        $query = BorrowTransaction::with(['book', 'user', 'request']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get()->map(function ($transaction) {
            return [
                'transaction_id' => $transaction->transaction_id,
                'request_id' => $transaction->request_id,
                'book' => [
                    'book_id' => $transaction->book->book_id,
                    'title' => $transaction->book->title,
                    'author' => $transaction->book->author,
                ],
                'borrow_date' => $transaction->borrow_date,
                'due_date' => $transaction->due_date,
                'return_date' => $transaction->return_date,
                'status' => $transaction->status,
                'days_overdue' => $transaction->days_overdue,
                'fine_amount' => $transaction->fine_amount,
                'fine_paid' => $transaction->fine_paid,
                'user_name' => $transaction->user->name ?? $transaction->user->full_name,
                'user_id' => $transaction->user_id,
            ];
        });
    }

    /**
     * Get all pending requests for a user
     */
    public function getUserRequests($userId = null)
    {
        $query = BorrowRequest::with('book');

        // If userId is provided, filter by that user
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query
            ->orderByDesc('requested_at')
            ->get()
            ->map(function ($request) {
                return [
                    'request_id' => $request->request_id,
                    'user_id' => $request->user_id,
                    'book' => [
                        'book_id' => $request->book->book_id,
                        'title' => $request->book->title,
                        'author' => $request->book->author,
                    ],
                    'status' => $request->status,
                    'queue_position' => $request->queue_position,
                    'requested_at' => $request->requested_at,
                    'approved_at' => $request->approved_at,
                    'expires_at' => $request->expires_at,
                    'days_remaining' => $request->status === 'approved' ? $request->daysUntilExpiry() : null,
                    'is_expired' => $request->hasExpired(),
                ];
            });
    }

    /**
     * Mark unpaid fines as paid
     */
    public function payFine($transactionId)
    {
        $transaction = BorrowTransaction::findOrFail($transactionId);

        if ($transaction->fine_amount <= 0 || $transaction->fine_paid) {
            throw ValidationException::withMessages([
                'fine' => ['No outstanding fine to pay.']
            ]);
        }

        $transaction->payFine();

        return [
            'transaction_id' => $transaction->transaction_id,
            'fine_amount' => $transaction->fine_amount,
            'fine_paid' => $transaction->fine_paid,
            'message' => 'Fine paid successfully.',
        ];
    }

    public function cleanupExpiredRequests()
    {
        $expired = BorrowRequest::where('status', 'approved')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expired as $request) {
            $this->expireRequest($request->request_id);
            $count++;
        }
        return ['expired_count' => $count];
    }

    /**
     * Expire a request manually (after 7 days)
     */
    public function expireRequest($requestId)
    {
        $request = BorrowRequest::findOrFail($requestId);

        if ($request->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Only approved requests can be expired.']
            ]);
        }

        return DB::transaction(function () use ($request) {
            $request->expire();

            // Promote next in queue
            $this->promoteNextInQueue($request->book_id);

            return [
                'request_id' => $request->request_id,
                'status' => $request->status,
                'message' => 'Request expired due to non-pickup.',
            ];
        });
    }
}
