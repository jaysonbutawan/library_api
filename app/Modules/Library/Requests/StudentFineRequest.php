<?php

namespace App\Modules\Library\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentFinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $studentId = $this->route('studentId');
        return isset($this->user()->student_id) && $this->user()->student_id == $studentId;
    }

    public function rules(): array
    {
        return [
            'include_paid' => ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'include_paid.integer' => 'Include_paid must be 0 or 1.',
            'include_paid.in' => 'Include_paid value must be 0 (unpaid) or 1 (all).',
        ];
    }
}
