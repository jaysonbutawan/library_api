<?php

namespace App\Modules\Library\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_id' => [
                'required',
                'exists:borrow_transactions,transaction_id'
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_id.required' => 'Transaction ID is required.',
            'transaction_id.exists' => 'Transaction does not exist.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.'
        ];
    }
}