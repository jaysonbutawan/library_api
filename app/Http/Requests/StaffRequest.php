<?php

namespace App\Modules\Library\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
    }
}

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|unique:library_staff,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:librarian,assistant',
            'status' => 'required|in:active,inactive',
        ];
    }
}

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('staff'); // route param
        return [
            'full_name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|unique:library_staff,email,' . $staffId . ',staff_id',
            'password' => 'sometimes|nullable|string|min:6',
            'role' => 'sometimes|required|in:librarian,assistant',
            'status' => 'sometimes|required|in:active,inactive',
        ];
    }
}