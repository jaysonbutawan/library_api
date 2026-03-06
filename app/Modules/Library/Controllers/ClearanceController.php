<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Library\Models\BorrowTransaction;
use App\Modules\Library\Models\Fine;

class ClearanceController extends Controller
{
    public function check($id)
    {
        $member = User::find($id);

        if (!$member) {
            return response()->json([
                'message' => 'Library member not found'
            ], 404);
        }

        $activeBorrows = BorrowTransaction::where('id', $member->id)
            ->whereIn('status', ['borrowed', 'overdue'])
            ->count();

        $unpaidFines = Fine::whereHas('transaction', function ($q) use ($member) {
            $q->where('id', $member->id);
        })
        ->where('paid_status', 'unpaid')
        ->sum('amount');

        $isClear = $activeBorrows === 0 && $unpaidFines == 0;

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'active_borrows' => $activeBorrows,
            'unpaid_fines' => $unpaidFines,
            'clearance_status' => $isClear ? 'clear' : 'not clear'
        ]);
    }
}