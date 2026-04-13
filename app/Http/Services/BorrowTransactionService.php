<?php

namespace App\Http\Services;

use App\Models\User;
use App\Models\BorrowTransaction;
use App\Models\BorrowRequest;
use App\Models\Book;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BorrowTransactionService
{

    public function requestBook(array $data)
    {
        $member = User::find($data['user_id']);
        $book = Book::find($data['book_id']);

        $genericMessage = 'Unable to process your request at this time.';

        // Validate user
        if (!$member || $member->status !== 'active') {
            throw ValidationException::withMessages([
                'user_id' => [$genericMessage]
            ]);
        }

        if (!$book || $book->status === 'deleted') {
            throw ValidationException::withMessages([
                'book_id' => [$genericMessage]
            ]);
        }

        // Only block if STILL pending
        $existingPendingRequest = BorrowRequest::where('user_id', $member->id)
            ->where('book_id', $book->book_id)
            ->where('status', 'pending')
            ->exists();

        //Only block if STILL actively borrowed
        $activeBorrow = BorrowTransaction::where('user_id', $member->id)
            ->where('book_id', $book->book_id)
            ->whereIn('status', ['borrowed', 'overdue'])
            ->exists();

        if ($existingPendingRequest) {
            throw ValidationException::withMessages([
                'book_id' => ['You already have a pending request for this book.']
            ]);
        }

        if ($activeBorrow) {
            throw ValidationException::withMessages([
                'book_id' => ['You still have this book. Please return it first.']
            ]);
        }

        return DB::transaction(function () use ($member, $book) {

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
                'message' => "Request submitted successfully.",
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

        if ($book->available_copies <= 0) {
            throw ValidationException::withMessages([
                'available_copies' => ['No copies available at this moment.']
            ]);
        }

        $nextInQueue = BorrowRequest::where('book_id', $request->book_id)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->first();

        if (!$nextInQueue) {
            throw ValidationException::withMessages([
                'queue' => ["No pending requests found for this book."]
            ]);
        }

        if ((int)$nextInQueue->request_id !== (int)$requestId) {
            $position = $request->queue_position;
            throw ValidationException::withMessages([
                'queue' => ["Cannot approve. Request #{$position} is not first in queue for this book."]
            ]);
        }

        // Validate pickup_days from the request payload
        $pickupDays = request()->input('pickup_days');

        if (!$pickupDays || !is_numeric($pickupDays) || (int)$pickupDays < 1 || (int)$pickupDays > 30) {
            throw ValidationException::withMessages([
                'pickup_days' => ['Pickup days must be between 1 and 30.']
            ]);
        }

        return DB::transaction(function () use ($request, $pickupDays, $book) {

            //LOCK row to prevent race condition
            $book->refresh();

            if ($book->available_copies <= 0) {
                throw ValidationException::withMessages([
                    'available_copies' => ['No copies available at this moment.']
                ]);
            }

            //RESERVE COPY HERE
            $book->decrement('available_copies');

            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
                'expires_at' => now()->addDays($pickupDays),
            ]);

            return [
                'request_id' => $request->request_id,
                'status'     => 'approved_reserved',
                'expires_at' => $request->expires_at,
                'message'    => "Reserved! Student has {$pickupDays} day(s) to pick up.",
            ];
        });
    }

    /**
     * Step: Librarian rejects a student's request
     */
    public function rejectRequest($requestId)
    {
        $request = BorrowRequest::findOrFail($requestId);

        return DB::transaction(function () use ($request) {
            // Mark the request as rejected
            $request->reject();
            // Promote the next pending request in queue
            $this->promoteNextInQueue($request->book_id);

            return [
                'request_id' => $request->request_id,
                'status' => $request->status,
                'message' => 'Request has been cancelled (rejected).',
            ];
        });
    }
    /**
     * Step 3: Student picks up the book (librarian completes the borrow)
     * This creates the transaction and decrements available_copies
     */
    public function completeBorrow($requestId, $dueDateDays)
    {
        $request = BorrowRequest::with('book')->findOrFail($requestId);

        // Must be approved (reservation made)
        if ($request->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Request must be approved before completing borrow.']
            ]);
        }

        // Check reservation expiry
        if ($request->expires_at && now()->greaterThan($request->expires_at)) {
            throw ValidationException::withMessages([
                'status' => ['Reservation has expired.']
            ]);
        }

        // Ensure user does not have active borrow
        $activeBorrow = BorrowTransaction::where('user_id', $request->user_id)
            ->where('book_id', $request->book_id)
            ->whereIn('status', ['borrowed', 'overdue'])
            ->first();

        if ($activeBorrow) {
            throw ValidationException::withMessages([
                'book_id' => ['You still have an active borrow for this book. Please return it first.']
            ]);
        }

        // Ensure request is not already completed
        if ($request->status === 'completed') {
            throw ValidationException::withMessages([
                'request_id' => ['This request is already completed. Please create a new request.']
            ]);
        }

        return DB::transaction(function () use ($request, $dueDateDays) {
            // Create the borrow transaction
            $transaction = BorrowTransaction::create([
                'user_id' => $request->user_id,
                'book_id' => $request->book_id,
                'request_id' => $request->request_id,
                'borrow_date' => Carbon::now()->toDateString(),
                'due_date' => Carbon::now()->addDays($dueDateDays)->toDateString(),
                'status' => 'borrowed',
            ]);

            // Mark request as completed
            $request->update([
                'status' => 'completed',
            ]);

            // No decrement of available_copies here because already reserved

            // Optionally promote next in queue if you want auto-approval for next pending request
            $this->promoteNextInQueue($request->book_id);

            return [
                'transaction_id' => $transaction->transaction_id,
                'request_id' => $request->request_id,
                'user_id' => $request->user_id,
                'book_id' => $request->book_id,
                'borrow_date' => $transaction->borrow_date,
                'due_date' => $transaction->due_date,
                'message' => 'Book borrowed successfully!',
            ];
        });
    }

   public function cancelRequest($requestId)
{
    $request = BorrowRequest::with('book')->findOrFail($requestId);

    if ($request->status === 'cancelled') {
        throw ValidationException::withMessages([
            'status' => ['Request is already cancelled.']
        ]);
    }

    return DB::transaction(function () use ($request) {

        $book = Book::lockForUpdate()->find($request->book_id);

        // CASE 1: approved but still active reservation
        if ($request->status === 'approved') {

            // restore stock because it was reserved earlier
            $book->increment('available_copies');
        }

        // CASE 2: expired logic (your existing rule)
        if ($request->status === 'approved' && Carbon::now()->greaterThan($request->expires_at)) {
            $request->expire();
        } else {
            $request->cancel();
        }

        // promote next in queue
        $this->promoteNextInQueue($request->book_id);

        return [
            'request_id' => $request->request_id,
            'status' => $request->status,
            'message' => 'Request cancelled.',
        ];
    });
}

    /**
     * Student returns the book
     */
    public function returnBook(int $transaction_id, array $data = [])
    {
        $transaction = BorrowTransaction::with(['book', 'user', 'request'])
            ->findOrFail($transaction_id);

        if (!in_array($transaction->status, ['borrowed', 'overdue'])) {
            throw ValidationException::withMessages([
                'status' => ['This book has already been returned.']
            ]);
        }

        // ✅ Use startOfDay() on both sides to avoid fractional day calculation
        $returnDate = Carbon::now()->startOfDay();
        $dueDate = Carbon::parse($transaction->due_date)->startOfDay();

        $isOverdue = $returnDate->gt($dueDate);
        $daysOverdue = $isOverdue
            ? $dueDate->diffInDays($returnDate)
            : 0;

        $finePerDay = $data['fine_per_day'] ?? null;

        if ($isOverdue && (!$finePerDay || !is_numeric($finePerDay))) {
            throw ValidationException::withMessages([
                'fine_per_day' => ['You must provide a numeric fine per day for overdue books.']
            ]);
        }

        $fineAmount = $isOverdue
            ? $daysOverdue * (int) $finePerDay
            : 0;

        return DB::transaction(function () use ($transaction, $returnDate, $daysOverdue, $fineAmount, $finePerDay, $isOverdue) {

            // ✅ Delegate to model helper, passing finePerDay dynamically
            $transaction->completeReturn($returnDate, (int) ($finePerDay ?? 0));

            // Restore stock
            $transaction->book->increment('available_copies');

            // Promote next in queue
            $this->promoteNextInQueue($transaction->book_id);

            return [
                'transaction_id' => $transaction->transaction_id,
                'status'         => $transaction->fresh()->status,
                'return_date'    => $transaction->return_date,
                'days_overdue'   => $daysOverdue,
                'fine_amount'    => $fineAmount,
                'requires_payment' => $isOverdue,
            ];
        });
    }
    /**
     * Cancel a request (student changes mind or request expires)
     */

    /**
     * Helper: When a book is returned or request is cancelled,
     * automatically promote next person in queue
     */
    private function promoteNextInQueue($bookId)
    {
        $book = Book::find($bookId);
        if ($book->available_copies <= 0) return;
        $nextRequest = BorrowRequest::where('book_id', $bookId)
            ->where('status', 'pending')
            ->orderBy('queue_position')
            ->first();
        if ($nextRequest) {
            $nextRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'expires_at' => now()->addDays(2),
            ]);

            $book->decrement('available_copies');
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
    public function getBorrowTransactionsByMemberId($userId = null, int $perPage = 10)
    {
        $query = BorrowTransaction::with(['book', 'user', 'request']);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($status = request('status')) {
            if ($status !== 'all') {
                if ($status === 'returned') {
                    $query->whereNotNull('return_date');
                } else {
                    $query->where('status', $status);
                }
            }
        }

        $query->orderBy('transaction_id');

        $transactions = $query->cursorPaginate($perPage);

        return [
            'data' => collect($transactions->items())->map(function ($transaction) {
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
                    'amount' => $transaction->amount,
                    'fine_paid' => $transaction->fine_paid,
                ];
            }),

            'meta' => [
                'next_cursor' => optional($transactions->nextCursor())->encode(),
                'prev_cursor' => optional($transactions->previousCursor())->encode(),
                'per_page' => $transactions->perPage(),
            ],
        ];
    }

    /**
     * Get all borrow transactions for a specific book with cursor pagination
     */
    public function getTransactionsByBookId(int $bookId, int $perPage = 10)
    {
        // 1. Build the query with necessary relationships
        $query = BorrowTransaction::with(['book', 'user'])
            ->where('book_id', $bookId);

        // 2. Optional: Add status filtering if provided in the request
         if ($status = request('status')) {
            if ($status !== 'all') {
                if ($status === 'returned') {
                    $query->whereNotNull('return_date');
                } else {
                    $query->where('status', $status);
                }
            }
        }

        // 3. Apply Cursor Pagination (Requires a unique, sequential order)
        // We use transaction_id to ensure the cursor is stable
        $transactions = $query->orderBy('transaction_id', 'desc')
            ->cursorPaginate($perPage);

        // 4. Transform the data to match your requested structure
        return [
            'data' => collect($transactions->items())->map(function ($transaction) {
                return [
                    'transaction_id' => $transaction->transaction_id,

                    // Nested book details as requested
                    'book' => [
                        'book_id' => $transaction->book->book_id,
                        'isbn'    => $transaction->book->isbn,
                        'title'   => $transaction->book->title,
                        'author'  => $transaction->book->author,
                    ],

                    // Borrower details (useful for admin views)
                    'borrower' => [
                        'user_id'   => $transaction->user->id,
                        'full_name' => $transaction->user->full_name ?? $transaction->user->name,
                    ],

                    'borrow_date'  => $transaction->borrow_date,
                    'due_date'     => $transaction->due_date,
                    'return_date'  => $transaction->return_date,
                    'status'       => $transaction->status,

                    // Fine and Overdue Logic
                    'days_overdue' => $transaction->days_overdue ?? 0,
                    'fine_amount'  => $transaction->fine_amount ?? 0,
                    'fine_paid'    => (bool)$transaction->fine_paid,
                ];
            }),

            // Cursor Metadata for the frontend
            'meta' => [
                'next_cursor' => optional($transactions->nextCursor())->encode(),
                'prev_cursor' => optional($transactions->previousCursor())->encode(),
                'per_page'    => $transactions->perPage(),
                'path'        => $transactions->path(),
            ],
        ];
    }

    /**
     * Get all pending requests for a user
     */
    public function getUserRequests($userId = null, int $perPage = 10)
    {
        $query = BorrowRequest::with(['book', 'user']);
        // USER FILTER
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        // SEARCH FILTER
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                })
                    ->orWhereHas('book', function ($bq) use ($search) {
                        $bq->where('title', 'like', "%{$search}%")
                            ->orWhere('author', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = request('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($expired = request('expired')) {
            if ($expired === 'true') {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            }

            if ($expired === 'false') {
                $query->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                });
            }
        }


        $query->orderByDesc('requested_at')
            ->orderByDesc('request_id');


        $requests = $query->cursorPaginate($perPage);

        return [
            'data' => collect($requests->items())->map(function ($request) {
                return [
                    'request_id' => $request->request_id,
                    'user_id' => $request->user_id,
                    'student_id' => $request->user?->student_id,
                    'full_name' => $request->user?->full_name,

                    'book' => $request->book ? [
                        'book_id' => $request->book->book_id,
                        'title' => $request->book->title,
                        'author' => $request->book->author,
                        'available_copies' => $request->book->available_copies,
                    ] : null,

                    'status' => $request->status,
                    'queue_position' => $request->queue_position,
                    'requested_at' => $request->requested_at,
                    'approved_at' => $request->approved_at,
                    'expires_at' => $request->expires_at,

                    'days_remaining' => $request->status === 'approved'
                        ? $request->daysUntilExpiry()
                        : null,

                    'is_expired' => $request->hasExpired(),
                ];
            }),

            'meta' => [
                'next_cursor' => optional($requests->nextCursor())->encode(),
                'prev_cursor' => optional($requests->previousCursor())->encode(),
                'per_page' => $requests->perPage(),
            ],
        ];
    }

    // public function payFine($transactionId)
    // {
    //     $transaction = BorrowTransaction::findOrFail($transactionId);

    //     if ($transaction->fine_amount <= 0 || $transaction->fine_paid) {
    //         throw ValidationException::withMessages([
    //             'fine' => ['No outstanding fine to pay.']
    //         ]);
    //     }

    //     $transaction->payFine();

    //     return [
    //         'transaction_id' => $transaction->transaction_id,
    //         'fine_amount' => $transaction->fine_amount,
    //         'fine_paid' => $transaction->fine_paid,
    //         'message' => 'Fine paid successfully.',
    //     ];
    // }

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

    public function expireRequest($requestId)
    {
        $request = BorrowRequest::with('book')->findOrFail($requestId);
        if ($request->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => ['Only approved requests can be expired.']
            ]);
        }
        return DB::transaction(function () use ($request) {
            $book = Book::lockForUpdate()->find($request->book_id);
            $book->increment('available_copies');
            $request->update([
                'status' => 'expired',
            ]);
            $this->promoteNextInQueue($book->book_id);
            return [
                'request_id' => $request->request_id,
                'status' => 'expired',
                'message' => 'Request expired and slot reassigned to next in queue.',
            ];
        });
    }
}
