<?php

namespace App\Http\Services;

use App\Models\Fine;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class FinesService
{
    public function getFines(?string $studentId = null)
    {
        $query = Fine::query()
            ->with(['user', 'transaction']);

        // If student ID is provided, filter it
        if ($studentId) {
            $query->whereHas('user', function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            });
        }

        $fines = $query->orderBy('created_at', 'desc')->get();

        // Transform data for cashier UI
        return $fines->map(function ($fine) {
            return [
                'fine_id' => $fine->fine_id,
                'student' => [
                    'student_id' => $fine->user?->student_id,
                    'full_name' => $fine->user?->full_name,
                    'department' => $fine->user?->department,
                ],
                'transaction_id' => $fine->transaction_id,
                'amount' => $fine->amount,
                'last_paid_at' => $fine->last_paid_at,
                'is_fully_paid' => $fine->is_fully_paid,
                'status' => $fine->is_fully_paid ? 'paid' : 'unpaid',
            ];
        });
    }

      public function payFine(int $fineId, float $paymentAmount)
    {
        return DB::transaction(function () use ($fineId, $paymentAmount) {

            $fine = Fine::lockForUpdate()->findOrFail($fineId);

            //Already fully paid
            if ($fine->is_fully_paid) {
                throw new \Exception('Fine is already fully paid.');
            }

            //Prevent overpayment
            if ($paymentAmount > $fine->amount) {
                throw new \Exception('Payment exceeds remaining balance.');
            }

            //Deduct payment
            $fine->amount = $fine->amount - $paymentAmount;

            // update last payment timestamp
            $fine->last_paid_at = Carbon::now();

            //Mark as fully paid if balance is 0
            if ($fine->amount <= 0) {
                $fine->amount = 0;
                $fine->is_fully_paid = true;
            }

            $fine->save();

            return [
                'fine_id' => $fine->fine_id,
                'remaining_balance' => $fine->amount,
                'is_fully_paid' => $fine->is_fully_paid,
                'last_paid_at' => $fine->last_paid_at,
            ];
        });
    }

}
