<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->input('type')) {
            'staff' => [
                'type'     => ['required', 'in:staff,student'],
                'email'    => ['required', 'email'],
                'password' => ['required', 'string'],
            ],
            'student' => [
                'type'       => ['required', 'in:staff,student'],
                'email' => ['required', 'email'],
                'password'   => ['required', 'string'],
            ],
            default => [
                'type' => ['required', 'in:staff,student'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Account type is required.',
            'type.in'       => 'Account type must be staff or student.',
            'email.required'      => 'Email is required.',
            'email.email'         => 'Please provide a valid email address.',
            'password.required'   => 'Password is required.',
        ];
    }

    // Return JSON instead of a redirect on validation failure
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
