<?php

namespace App\Http\Requests\Practice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnswerQuestionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'selected_options' => ['required', 'array', 'min:1'],
            'selected_options.*' => ['required', 'integer', 'distinct', 'exists:question_options,id'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
