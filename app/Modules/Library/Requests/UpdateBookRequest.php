<?php

namespace App\Modules\Library\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bookId = $this->route('id');

        return [
            'isbn' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('books', 'isbn')
                    ->ignore($bookId, 'book_id')
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('books')
                    ->where(function ($query) {
                        return $query->where('isbn', $this->isbn);
                    })
                    ->ignore($bookId, 'book_id')
            ],

            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'total_copies' => ['sometimes', 'required', 'integer', 'min:1'],
            'available_copies' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title cannot be empty.',
            'author.required' => 'Author cannot be empty.',
            'total_copies.min' => 'Total copies must be at least 1.',
            'isbn.unique' => 'This ISBN already exists.',
            'title.unique' => 'A book with this title and ISBN already exists.'
        ];
    }
}
