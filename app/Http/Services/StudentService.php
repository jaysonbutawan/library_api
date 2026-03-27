<?php

namespace App\Http\Services;

use App\Models\User;

class StudentService
{
    public function getStudents(?int $id = null)
    {
        $query = User::whereHas('role', function ($q) {
            $q->whereRaw('LOWER(name) = ?', ['student']);
        })
            ->withCount([
                'borrowTransactions as total_borrowed',
                'borrowTransactions as current_books' => function ($q) {
                    $q->where('status', 'borrowed');
                },
            ])
            ->with(['fines' => function ($q) {
                $q->where('paid_status', 'unpaid');
            }]);

        if ($id) {
            $student = $query->where('id', $id)->firstOrFail();
            return $this->formatStudent($student);
        }

        return $query->get()->map(fn($student) => $this->formatStudent($student));
    }

    private function formatStudent(User $student): array
    {
        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'student_id' => $student->student_id,
            'department' => $student->department,
            'total_borrowed' => $student->total_borrowed,
            'current_books' => $student->current_books,
            'fines' => $student->fines->sum('amount'),
        ];
    }
}
