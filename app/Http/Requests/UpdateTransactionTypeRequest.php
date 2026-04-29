<?php

namespace App\Http\Requests;

use App\Enums\TransactionAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'action' => ['sometimes', Rule::enum(TransactionAction::class)],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
