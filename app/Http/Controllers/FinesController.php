<?php

namespace App\Http\Controllers;

use App\Http\Services\FinesService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FinesController extends Controller
{
   private FinesService $service;

    public function __construct(FinesService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all fines or filter by student_id
     */
    public function index(Request $request)
    {
        $studentId = $request->query('student_id');

        return response()->json(
            $this->service->getFines($studentId)
        );
    }

    /**
     * Pay fine (partial or full)
     */
    public function pay(Request $request)
    {
        $request->validate([
            'fine_id' => 'required|integer',
            'amount' => 'required|numeric|min:1'
        ]);

        try {
            $result = $this->service->payFine(
                $request->fine_id,
                $request->amount
            );

            return response()->json([
                'message' => 'Payment successful.',
                'data' => $result
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Fine not found.'
            ], 404);

        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            $status = ($code >= 400 && $code < 600) ? $code : 500;

            return response()->json([
                'message' => $e->getMessage()
            ], $status);
        }
    }
}
