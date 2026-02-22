<?php

namespace App\Modules\Library\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules()
    {
        $bookId = $this->route('id');
        return [
            'title' => [
                'required',
                'required',
                'string',
                'max:255',
                Rule::unique('books')
                    ->where(function ($query) {
                        return $query->where('isbn', $this->isbn);
                    })
                    ->ignore($bookId, 'book_id')
            ],
            'isbn' => ['required', 'string', 'unique:books,isbn'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'total_copies' => ['required', 'integer', 'min:1'],
            'available_copies' => ['nullable', 'integer', 'min:0']
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBN is required.',
            'isbn.unique' => 'This ISBN already exists.',
            'title.required' => 'Title cannot be empty.',
            'author.required' => 'Author cannot be empty.',
            'total_copies.min' => 'Total copies must be at least 1.',
            'title.unique' => 'A book with this title and ISBN already exists.'
        ];
    }
}
