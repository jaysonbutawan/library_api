<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Services\LibraryStaffService;

class LibraryStaffController extends Controller
{
    protected LibraryStaffService $staffService;

    public function __construct(LibraryStaffService $staffService)
    {
        $this->staffService = $staffService;
    }
    public function show(?int $id = null)
    {
        $staff = $this->staffService->getStaff($id);

        if (!$staff) {
            return response()->json([
                'message' => 'Staff not found'
            ], 404);
        }

        return response()->json($staff);
    }

    public function store(StoreStaffRequest $request)
    {
        $staff = $this->staffService->create($request->validated());
        return response()->json([
            'message' => 'Staff created successfully',
            'staff' => $staff
        ], 201);
    }

    public function update(UpdateStaffRequest $request, User $staff)
    {
        $staff = $this->staffService->update($staff, $request->validated());
        return response()->json([
            'message' => 'Staff updated successfully',
            'staff' => $staff
        ]);
    }

    public function destroy(User $staff)
    {
        $this->staffService->delete($staff);
        return response()->json(['message' => 'Staff deleted successfully']);
    }
}
