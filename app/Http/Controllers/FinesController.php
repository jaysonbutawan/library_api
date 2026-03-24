<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayFineRequest;
use App\Http\services\FinesService;
use Illuminate\Http\Request;

class FinesController extends Controller
{
    public function __construct(
        protected FinesService $finesService
    ) {}

    public function finesChoice(Request $request)
    {
        $studentId = $request->query('student_id');
        $department = $request->query('department');

        $result = $this->finesService->listFines($studentId, $department);

        return response()->json($result);
    }

    public function payFine(PayFineRequest $request, $fineId)
    {
        $validated = $request->validated();
        $paidAmount = $validated['amount'];

        $result = $this->finesService->payFine($fineId, $paidAmount);

        return response()->json(
            $result['ok'] ? ['message' => $result['message'], 'data' => $result['data']] : ['message' => $result['message']],
            $result['status']
        );
    }

    public function memberFines($memberId)
    {
        $result = $this->finesService->listMemberFines($memberId);
        return response()->json($result);
    }

    public function unpaidFines()
    {
        $result = $this->finesService->listUnpaidFines();
        return response()->json($result);
    }
}
