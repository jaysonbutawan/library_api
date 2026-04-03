<?php

namespace App\Http\Requests;

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
                Rule::unique('books', 'isbn')->ignore($bookId, 'book_id')
            ],

            'title' => ['sometimes','required','string','max:255',
                        Rule::unique('books', 'title')->ignore($bookId, 'book_id')
            ],

            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,category_id'],
            'publication_year' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'total_copies' => ['sometimes', 'required', 'integer', 'min:1'],
            'available_copies' => ['sometimes', 'nullable', 'integer', 'min:0', 'lte:total_copies'],
            'status' => ['sometimes', 'nullable', 'string', 'in:available,borrowed,unavailable,maintenance'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Title cannot be empty.',
            'title.unique' => 'This title already exists.',
            'author.required' => 'Author cannot be empty.',
            'total_copies.min' => 'Total copies must be at least 1.',
            'isbn.unique' => 'This ISBN already exists.',
            'publication_year.integer' => 'Publication year must be a valid year.',
            'available_copies.lte' => 'Available copies cannot exceed the total number of copies.',
        ];
    }
}
