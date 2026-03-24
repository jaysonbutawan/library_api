<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
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
