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
        // Get the book ID from URL: /books/{id}
        $bookId = $this->route('book_id');

        return [
            'isbn' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('books', 'isbn')
                    ->ignore($bookId, 'book_id')  // Ignore current book
            ],
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('books', 'title')
                    ->ignore($bookId, 'book_id')  // Ignore current book
            ],
            'author' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                'max:255'
            ],
            'publication_year' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1000',
                'max:' . now()->year
            ],
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,category_id'
            ],
            'total_copies' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:9999'
            ],
            'available_copies' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'lte:total_copies'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.unique' => 'This ISBN is already used by another book.',
            'title.unique' => 'This title is already used by another book.',
            'isbn.required' => 'ISBN is required.',
            'title.required' => 'Title is required.',
            'author.required' => 'Author is required.',
            'total_copies.required' => 'Total copies is required.',
            'total_copies.min' => 'Total copies must be at least 1.',
            'publication_year.max' => 'Publication year cannot be in the future.',
            'available_copies.lte' => 'Available copies cannot be greater than total copies.',
            'category_id.exists' => 'Selected category does not exist.',
            'isbn.exists' => 'The selected ISBN does not exist.',
            'book_id.exists' => 'The selected book does not exist.'
        ];
    }

    protected function prepareForValidation()
    {
        // Convert empty strings to null
        if ($this->publication_year === '') {
            $this->merge(['publication_year' => null]);
        }

        if ($this->category_id === '') {
            $this->merge(['category_id' => null]);
        }

        if ($this->available_copies === '') {
            $this->merge(['available_copies' => null]);
        }

        // Convert strings to integers
        if ($this->total_copies) {
            $this->merge(['total_copies' => (int)$this->total_copies]);
        }

        if ($this->available_copies) {
            $this->merge(['available_copies' => (int)$this->available_copies]);
        }

        if ($this->publication_year) {
            $this->merge(['publication_year' => (int)$this->publication_year]);
        }

        if ($this->category_id) {
            $this->merge(['category_id' => (int)$this->category_id]);
        }
    }
}
