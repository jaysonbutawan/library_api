<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'isbn' => [
                'required',
                'string',
                'unique:books,isbn'  // Simple: ISBN must be unique
            ],
            'title' => [
                'required',
                'string',
                'max:255',
                'unique:books,title'  // Simple: Title must be unique
            ],
            'author' => [
                'required',
                'string',
                'max:255'
            ],
            'status' => [
                'nullable',
                'string',
                'max:255'  // Restrict to valid values
            ],
            'publication_year' => [
                'nullable',
                'integer',
                'digits:4',
                'min:1000',
                'max:' . now()->year  // Can't be future year
            ],
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,category_id'
            ],
            'total_copies' => [
                'required',
                'integer',
                'min:1',
                'max:9999'
            ],
            'available_copies' => [
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
            'isbn.required' => 'ISBN is required.',
            'isbn.unique' => 'Please provide a unique ISBN.',
            'isbn.string' => 'ISBN must be a valid string.',

            'title.required' => 'Title is required.',
            'title.unique' => 'A book with this title already exists.',
            'title.max' => 'Title cannot exceed 255 characters.',

            'author.required' => 'Author is required.',
            'author.max' => 'Author name cannot exceed 255 characters.',

            'status.in' => 'Status must be one of: available, unavailable, borrowed.',

            'publication_year.integer' => 'Publication year must be a valid year.',
            'publication_year.min' => 'Publication year must be 1000 or later.',
            'publication_year.max' => 'Publication year cannot be in the future.',
            'publication_year.digits' => 'Publication year must be a 4-digit year.',

            'category_id.exists' => 'Selected category does not exist.',
            'category_id.integer' => 'Category ID must be a number.',

            'total_copies.required' => 'Total copies is required.',
            'total_copies.integer' => 'Total copies must be a whole number.',
            'total_copies.min' => 'Total copies must be at least 1.',
            'total_copies.max' => 'Total copies cannot exceed 9999.',

            'available_copies.integer' => 'Available copies must be a whole number.',
            'available_copies.min' => 'Available copies cannot be negative.',
            'available_copies.lte' => 'Available copies cannot be greater than total copies.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert null strings to actual null
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
