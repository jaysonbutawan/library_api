<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\LibraryMember;
use App\Modules\Library\Models\BorrowTransaction;
use App\Modules\Library\Models\Fine;

class ClearanceController extends Controller
{
    // Check library clearance for a student
    public function check($memberId)
    {
        $member = LibraryMember::find($memberId);

        if (!$member) {
            return response()->json(['message' => 'Library member not found'], 404);
        }

        // Check borrowed books
        $activeBorrows = BorrowTransaction::where('library_member_id', $memberId)
            ->whereIn('status', ['borrowed', 'overdue'])
            ->count();

        // Check unpaid fines
        $unpaidFines = Fine::whereHas('transaction', function ($q) use ($memberId) {
            $q->where('library_member_id', $memberId);
        })->where('paid_status', 'unpaid')->sum('amount');

        $isClear = $activeBorrows === 0 && $unpaidFines == 0;

        return response()->json([
            'member_id' => $memberId,
            'member_name' => $member->student->full_name ?? null,
            'active_borrows' => $activeBorrows,
            'unpaid_fines' => $unpaidFines,
            'clearance_status' => $isClear ? 'clear' : 'not clear'
        ]);
    }
}
