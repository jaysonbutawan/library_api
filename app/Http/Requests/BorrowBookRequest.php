<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BorrowBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id'
            ],
            'book_id' => [
                'required',
                'exists:books,book_id'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Library member is required.',
            'user_id.exists' => 'Library member does not exist.',
            'book_id.required' => 'Book is required.',
            'book_id.exists' => 'Book does not exist.',
        ];
    }
}