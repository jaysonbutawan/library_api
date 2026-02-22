<?php

namespace App\Modules\Library\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Requests\StaffLoginRequest;
use App\Modules\Library\Requests\StoreStaffRequest;
use App\Modules\Library\Requests\UpdateStaffRequest;
use App\Modules\Library\Services\LibraryStaffService;
use App\Modules\Library\Models\LibraryStaff;

class LibraryStaffController extends Controller
{
    protected LibraryStaffService $staffService;

    public function __construct(LibraryStaffService $staffService)
    {
        $this->staffService = $staffService;
    }

    public function login(StaffLoginRequest $request)
    {
        $staff = $this->staffService->login($request->email, $request->password);

        if (!$staff) {
            return response()->json(['message' => 'Invalid credentials or inactive account'], 401);
        }

        $token = $staff->createToken('staff-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'staff' => [
                'id' => $staff->staff_id,
                'name' => $staff->full_name,
                'email' => $staff->email,
                'role' => $staff->role,
                'status' => $staff->status
            ]
        ]);
    }

    public function index()
    {
        return response()->json(LibraryStaff::all());
    }

    public function store(StoreStaffRequest $request)
    {
        $staff = $this->staffService->create($request->validated());
        return response()->json([
            'message' => 'Staff created successfully',
            'staff' => $staff
        ], 201);
    }

    public function update(UpdateStaffRequest $request, LibraryStaff $staff)
    {
        $staff = $this->staffService->update($staff, $request->validated());
        return response()->json([
            'message' => 'Staff updated successfully',
            'staff' => $staff
        ]);
    }

    public function destroy(LibraryStaff $staff)
    {
        $this->staffService->delete($staff);
        return response()->json(['message' => 'Staff deleted successfully']);
    }

    public function show(LibraryStaff $staff)
    {
        return response()->json($staff);
    }
}