<?php

namespace App\Modules\Library\Services;

use App\Modules\Library\Models\Fine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinesService
{
    /**
     * Admin filtering endpoint:
     * filter by student_id and/or department
     */
    public function listFines(?string $studentId = null, ?string $department = null): array
    {
        $query = Fine::query()->with(['transaction.member', 'transaction.book']);

        if ($studentId !== null && $studentId !== '') {
            $query->whereHas('transaction', fn($q) => $q->where('id', $studentId));
        }

        if ($department !== null && $department !== '') {
            $query->whereHas('transaction.member', fn($q) => $q->where('department', $department));
        }

        $fines = $query->get();

        $data = $fines->map(fn(Fine $fine) => [
            'fine_id' => $fine->fine_id,
            'book_title' => optional($fine->transaction?->book)->title,
            'student_id' => optional($fine->transaction?->member)->student_id,
            'student_name' => optional($fine->transaction?->member)->full_name,
            'department' => optional($fine->transaction?->member)->department,
            'amount' => $fine->amount,
            'status' => $fine->paid_status,
        ])->values();

        return [
            'data' => $data,
            'count' => $data->count(),
        ];
    }

    // Pay a fine: validates payment >= fine amount and updates paid_status.
    public function payFine(int|string $fineId, float|int $paidAmount): array
    {
        $fine = Fine::query()->find($fineId);

        if (!$fine) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'Fine not found',
            ];
        }

        if ($paidAmount < $fine->amount) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Payment amount is less than fine',
            ];
        }

        DB::transaction(function () use ($fine) {
            if ($fine->paid_status !== 'paid') {
                $fine->update(['paid_status' => 'paid']);
            }
        });

        $fine->refresh();

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Fine payment successful',
            'data' => [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'status' => $fine->paid_status,
            ],
        ];
    }

    // Member fines list (all statuses) with member info.

    public function listMemberFines(int|string $memberId): Collection
    {
        $fines = Fine::query()
            ->whereHas('transaction', fn($q) => $q->where('library_member_id', $memberId))
            ->with('transaction.member')
            ->get();

        return $fines->map(function (Fine $fine) {
            $member = $fine->transaction?->member;

            return [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'paid_status' => $fine->paid_status,
                'transaction_id' => $fine->transaction_id,
                'student_name' => $member?->full_name,
                'student_department' => $member?->department,
                'student_email' => $member?->email,
            ];
        })->values();
    }

    // Unpaid fines list with member info.

    public function listUnpaidFines(): Collection
    {
        $fines = Fine::query()
            ->where('paid_status', 'unpaid')
            ->with('transaction.member')
            ->get();

        return $fines->map(function (Fine $fine) {
            $member = $fine->transaction?->member;

            return [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'transaction_id' => $fine->transaction_id,
                'student_name' => $member?->full_name,
                'student_department' => $member?->department,
                'student_email' => $member?->email,
            ];
        })->values();
    }
}
