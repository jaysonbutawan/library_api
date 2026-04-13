<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class BorrowTransaction extends Model
{
    protected $primaryKey = 'transaction_id';
    protected $table = 'borrow_transactions';

    protected $fillable = [
        'user_id',
        'book_id',
        'request_id',
        'copy_number',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        'days_overdue',
        'fine_amount',
        'fine_paid',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'fine_amount' => 'decimal:2',
        'fine_paid' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id', 'book_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BorrowRequest::class, 'request_id', 'request_id');
    }

    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class, 'transaction_id', 'transaction_id');
    }

    /**
     * Scopes
     */

    public function scopeBorrowed($query)
    {
        return $query->where('status', 'borrowed');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'borrowed')->whereNull('return_date');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByBook($query, $bookId)
    {
        return $query->where('book_id', $bookId);
    }

    public function scopeWithUnpaidFines($query)
    {
        return $query->where('fine_paid', false)->whereNotNull('fine_amount')->where('fine_amount', '>', 0);
    }

    public function scopeDueThisWeek($query)
    {
        return $query->borrowed()
            ->whereBetween('due_date', [
                Carbon::now(),
                Carbon::now()->addWeek()
            ]);
    }

    /**
     * Accessors & Mutators
     */

    public function getIsBorrowedAttribute(): bool
    {
        return $this->status === 'borrowed';
    }

    public function getIsReturnedAttribute(): bool
    {
        return $this->status === 'returned';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'overdue' ||
               ($this->status === 'borrowed' && Carbon::now()->greaterThan($this->due_date));
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->status !== 'borrowed') {
            return 0;
        }
        $days = Carbon::now()->diffInDays($this->due_date, false);
        return max(0, $days);
    }

    /**
     * Helper methods
     */

   public function markAsOverdue(int $finePerDay = 0): void
{
    $daysOverdue = Carbon::now()->startOfDay()
        ->diffInDays(Carbon::parse($this->due_date)->startOfDay());

    $this->update([
        'status' => 'overdue',
        'days_overdue' => $daysOverdue,
        'fine_amount' => $daysOverdue * $finePerDay,
    ]);
}

public function completeReturn(?Carbon $returnDate = null, int $finePerDay = 0): void
{
    $returnDate = ($returnDate ?? Carbon::now())->startOfDay();
    $dueDate = Carbon::parse($this->due_date)->startOfDay();

    $isOverdue = $returnDate->gt($dueDate);
    $daysOverdue = $isOverdue ? $dueDate->diffInDays($returnDate) : 0;

    $this->update([
        'return_date' => $returnDate->toDateString(),
        'status' => 'returned',
        'days_overdue' => $daysOverdue,
        'fine_amount' => $isOverdue ? $daysOverdue * $finePerDay : null,
    ]);
}

    public function payFine(): void
    {
        if ($this->fine_amount > 0) {
            $this->update(['fine_paid' => true]);
        }
    }

    public function canBeReturned(): bool
    {
        return $this->status === 'borrowed' && $this->return_date === null;
    }

    public function getDueInDays(): int
    {
        if ($this->status !== 'borrowed') {
            return 0;
        }
        return max(-999, $this->due_date->diffInDays(Carbon::now(), false));
    }
}
