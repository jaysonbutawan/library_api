<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Services\ClearanceService;
use Illuminate\Http\Request;

class ClearanceController extends Controller
{
    private ClearanceService $service;

    public function __construct(ClearanceService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all students clearance list
     */
    public function index(Request $request)
    {
        return response()->json([
            'data' => $this->service->getStudentsClearanceList(
                $request->student_id // ✅ pass query param
            )
        ]);
    }
}
