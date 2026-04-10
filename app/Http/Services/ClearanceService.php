<?php

namespace App\Http\Services;

use App\Models\User;

class ClearanceService
{

    public function getStudentsClearanceList()
    {
        $students = User::query()
            ->whereNotNull('student_id')
            ->withSum('fines as total_fines', 'amount')
            ->withCount([
                'borrowTransactions as unreturned_books_count' => function ($query) {
                    $query->whereNull('return_date');
                }
            ])
            ->get();

        return $students->map(function ($student) {
            return [
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'department' => $student->department,
                'total_fines' => $student->total_fines ?? 0,
                'unreturned_books' => $student->unreturned_books_count,
                'is_cleared' => ($student->total_fines == 0 && $student->unreturned_books_count == 0),

                'remarks' => $this->getRemarks($student->total_fines, $student->unreturned_books_count),
            ];
        });
    }
    
    private function getRemarks($fines, $books)
    {
        if ($fines > 0 && $books > 0) {
            return 'Has unpaid fines and unreturned books';
        }

        if ($fines > 0) {
            return 'Has unpaid fines';
        }

        if ($books > 0) {
            return 'Has unreturned books';
        }

        return 'Cleared';
    }
}
