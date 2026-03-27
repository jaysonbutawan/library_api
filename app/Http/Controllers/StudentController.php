<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\StudentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class StudentController extends Controller
{
   private StudentService $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

   public function index($id = null)
{
    try {
        $students = $this->studentService->getStudents($id);

        return response()->json($students);
    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Student not found'
        ], 404);
    }
}
}
