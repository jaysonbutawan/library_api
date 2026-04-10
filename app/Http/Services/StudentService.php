<?php

namespace App\Http\Services;

use App\Models\User;

class StudentService
{
    public function getStudents(?int $id = null, int $perPage = 10)
    {
        $query = User::whereHas('role', function ($q) {
            $q->where('name', 'student');
        })
            ->withCount([
                'borrowTransactions as total_borrowed',
                'borrowTransactions as current_books' => function ($q) {
                    $q->where('status', 'borrowed');
                },
            ])
           ->withSum(['fines' => function ($q) {
            $q->where('is_fully_paid', false);
        }], 'amount');

        // =========================
        // 🔍 SEARCH FILTER
        // =========================
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        // =========================
        // 🏫 DEPARTMENT FILTER
        // =========================
        if ($department = request('department')) {
            if ($department !== 'all') {
                $query->where('department', $department);
            }
        }

        // =========================
        // 💰 STATUS FILTER
        // =========================
        if ($status = request('status')) {

            if ($status === 'fines') {
                // students WITH unpaid fines
                $query->whereHas('fines', function ($q) {
                    $q->where('is_fully_paid', false);
                });
            }

            if ($status === 'clear') {
                // students WITHOUT unpaid fines
                $query->whereDoesntHave('fines', function ($q) {
                    $q->where('is_fully_paid', false);
                });
            }
        }

        //REQUIRED for cursor pagination
        $query->orderBy('users.id');

        // =========================
        // SINGLE STUDENT
        // =========================
        if ($id) {
            $student = $query->where('id', $id)->firstOrFail();
            return $this->formatStudent($student);
        }

        // =========================
        // CURSOR PAGINATION
        // =========================
        $students = $query->cursorPaginate($perPage);

        return [
            'data' => collect($students->items())
                ->map(fn($student) => $this->formatStudent($student)),

            'meta' => [
                'next_cursor' => optional($students->nextCursor())->encode(),
                'prev_cursor' => optional($students->previousCursor())->encode(),
                'per_page' => $students->perPage(),
            ],
        ];
    }

 private function formatStudent(User $student): array
{
    return [
        'id' => $student->id,
        'full_name' => $student->full_name,
        'student_id' => $student->student_id,
        'department' => $student->department,
        'total_borrowed' => $student->total_borrowed ?? 0,
        'current_books' => $student->current_books ?? 0,

        // Use the attribute created by withSum (default to 0 if null)
        'fines' => (float) ($student->fines_sum_amount ?? 0),
    ];
}
}
