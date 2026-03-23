<?php

namespace App\Modules\Library\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayFineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
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
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.'
        ];
    }
}
