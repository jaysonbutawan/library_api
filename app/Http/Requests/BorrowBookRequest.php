<?php

namespace App\Modules\Library\Requests;

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
            'library_member_id' => [
                'required',
                'exists:library_members,library_member_id'
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
            'library_member_id.required' => 'Library member is required.',
            'library_member_id.exists' => 'Library member does not exist.',
            'book_id.required' => 'Book is required.',
            'book_id.exists' => 'Book does not exist.',
        ];
    }
}