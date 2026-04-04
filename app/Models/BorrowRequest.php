<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class BorrowRequest extends Model
{
    protected $primaryKey = 'request_id';
    protected $table = 'borrow_requests';

    protected $fillable = [
        'user_id',
        'book_id',
        'status',
        'queue_position',
        'requested_at',
        'approved_at',
        'cancelled_at',
        'expires_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
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

    public function transaction(): HasOne
    {
        return $this->hasOne(BorrowTransaction::class, 'request_id', 'request_id');
    }

    /**
     * Scopes
     */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeByBook($query, $bookId)
    {
        return $query->where('book_id', $bookId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInQueue($query)
    {
        return $query->where('status', 'pending')->orderBy('queue_position');
    }

    public function scopeFirstInQueue($query, $bookId)
    {
        return $query->byBook($bookId)->pending()->orderBy('queue_position')->first();
    }

    /**
     * Accessors & Mutators
     */

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === 'approved' &&
               $this->expires_at &&
               Carbon::now()->greaterThan($this->expires_at);
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Helper methods
     */

    public function approve($pickupdays): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays($pickupdays),
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
        ]);
    }

    public function expire(): void
    {
        $this->update([
            'status' => 'expired',
            'cancelled_at' => Carbon::now(),
        ]);
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return max(0, Carbon::now()->diffInDays($this->expires_at, false));
    }

    public function hasExpired(): bool
    {
        return $this->isExpired;
    }
}
