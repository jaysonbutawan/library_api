<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\StudentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    private StudentService $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function index(Request $request, $id = null)
    {
        try {
            $perPage = min($request->input('per_page', 10), 50);

            $students = $this->studentService->getStudents($id, $perPage);

            return response()->json($students);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Student not found'
            ], 404);
        }
    }
}
