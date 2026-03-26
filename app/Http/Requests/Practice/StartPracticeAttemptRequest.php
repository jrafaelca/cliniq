<?php

namespace App\Http\Requests\Practice;

use App\Models\Attempt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPracticeAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => [
                'sometimes',
                'string',
                Rule::in([
                    Attempt::MODE_TRAINING,
                    Attempt::MODE_PRACTICE,
                    Attempt::MODE_SIMULATION,
                ]),
            ],
            'restart' => ['sometimes', 'boolean'],
        ];
    }
}
