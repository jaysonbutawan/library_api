<?php

namespace App\Http\Services;

use App\Models\Fine;
use Illuminate\Support\Collection;

class FinesService
{
    public function listFines(?string $studentId = null, ?string $department = null): array
    {
        $query = Fine::query()->with(['transaction.member', 'transaction.book']);

        if ($studentId !== null && $studentId !== '') {
            $query->whereHas('transaction.member', fn($q) => $q->where('student_id', $studentId));
        }

        if ($department !== null && $department !== '') {
            $query->whereHas('transaction.member', fn($q) => $q->where('department', $department));
        }

        $data = $query->get()
            ->map(fn(Fine $fine) => $this->formatAdminFine($fine))
            ->values();

        return [
            'data' => $data,
            'count' => $data->count(),
        ];
    }

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

        if ($fine->paid_status !== 'paid') {
            $fine->update(['paid_status' => 'paid']);
        }

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

    public function listMemberFines(int|string $memberId): Collection
    {
        return Fine::query()
            ->whereHas('transaction', fn($q) => $q->where('id', $memberId))
            ->with('transaction.member')
            ->get()
            ->map(fn(Fine $fine) => $this->formatMemberFine($fine, true))
            ->values();
    }

    public function listUnpaidFines(): Collection
    {
        return Fine::query()
            ->where('paid_status', 'unpaid')
            ->with('transaction.member')
            ->get()
            ->map(fn(Fine $fine) => $this->formatMemberFine($fine, false))
            ->values();
    }

    protected function formatAdminFine(Fine $fine): array
    {
        $transaction = $fine->transaction;
        $member = $transaction?->member;
        $book = $transaction?->book;

        return [
            'fine_id' => $fine->fine_id,
            'book_title' => $book?->title,
            'student_id' => $member?->student_id,
            'student_name' => $member?->full_name,
            'department' => $member?->department,
            'amount' => $fine->amount,
            'status' => $fine->paid_status,
        ];
    }

    protected function formatMemberFine(Fine $fine, bool $includeStatus = true): array
    {
        $member = $fine->transaction?->member;

        $data = [
            'fine_id' => $fine->fine_id,
            'amount' => $fine->amount,
            'transaction_id' => $fine->transaction_id,
            'student_name' => $member?->full_name,
            'student_department' => $member?->department,
            'student_email' => $member?->email,
        ];

        if ($includeStatus) {
            $data['paid_status'] = $fine->paid_status;
        }

        return $data;
    }
}