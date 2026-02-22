<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\Fine;
use App\Modules\Library\Requests\PayFineRequest;
use App\Modules\Library\Requests\StudentFinesRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinesController extends Controller
{
    public function studentFines(StudentFinesRequest $request, $studentId)
    {
        $includePaid = $request->query('include_paid', 1);

        $query = Fine::whereHas('transaction', fn($q) => $q->where('library_member_id', $studentId))
            ->with(['transaction.book']);

        if (!$includePaid) {
            $query->where('paid_status', 'unpaid');
        }

        $fines = $query->get()->map(fn($fine) => [
            'fine_id' => $fine->fine_id,
            'book_title' => $fine->transaction->book->title,
            'amount' => $fine->amount,
            'status' => $fine->paid_status
        ]);

        return response()->json([
            'student_id' => $studentId,
            'fines' => $fines
        ]);
    }

    public function finesChoice(Request $request)
    {
        $query = Fine::with(['transaction.member', 'transaction.book']);
        if ($request->has('student_id')) {
            $studentId = $request->query('student_id');
            $query->whereHas('transaction', fn($q) => $q->where('library_member_id', $studentId));
        }
        if ($request->has('department')) {
            $department = $request->query('department');
            $query->whereHas('transaction.member', fn($q) => $q->where('department', $department));
        }

        $fines = $query->get()->map(fn($fine) => [
            'fine_id' => $fine->fine_id,
            'book_title' => $fine->transaction->book->title,
            'student_id' => $fine->transaction->member->student_id,
            'student_name' => $fine->transaction->member->full_name,
            'department' => $fine->transaction->member->department,
            'amount' => $fine->amount,
            'status' => $fine->paid_status,
        ]);

        return response()->json([
            'data' => $fines,
            'count' => $fines->count()
        ]);
    }

    public function payFine(PayFineRequest $request, $fineId)
    {
        $fine = Fine::find($fineId);

        if (!$fine) {
            return response()->json([
                'message' => 'Fine not found'
            ], 404);
        }

        $validated = $request->validated();
        if ($validated['amount'] < $fine->amount) {
            return response()->json([
                'message' => 'Payment amount is less than fine'
            ], 400);
        }

        DB::transaction(function () use ($fine) {
            $fine->update(['paid_status' => 'paid']);
        });

        return response()->json([
            'message' => 'Fine payment successful',
            'data' => [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'status' => $fine->paid_status
            ]
        ]);
    }
    /**
     * Get all fines for a library member
     */
    public function memberFines($memberId)
    {
        $fines = Fine::whereHas('transaction', function ($q) use ($memberId) {
            $q->where('library_member_id', $memberId);
        })->with('transaction.member')->get(); 

        $result = $fines->map(function ($fine) {
            $member = $fine->transaction->member;
            return [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'paid_status' => $fine->paid_status,
                'transaction_id' => $fine->transaction_id,
                'student_name' => $member->full_name ?? null,
                'student_department' => $member->department ?? null,
                'student_email' => $member->email ?? null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get unpaid fines for cashier
     */
    public function unpaidFines()
    {
        $fines = Fine::where('paid_status', 'unpaid')->with('transaction.member')->get();

        $result = $fines->map(function ($fine) {
            $member = $fine->transaction->member;
            return [
                'fine_id' => $fine->fine_id,
                'amount' => $fine->amount,
                'transaction_id' => $fine->transaction_id,
                'student_name' => $member->full_name ?? null,
                'student_department' => $member->department ?? null,
                'student_email' => $member->email ?? null,
            ];
        });

        return response()->json($result);
    }
}
