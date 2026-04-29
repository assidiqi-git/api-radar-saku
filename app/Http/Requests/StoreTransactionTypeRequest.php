<?php

namespace App\Http\Requests;

use App\Enums\TransactionAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'action' => ['required', Rule::enum(TransactionAction::class)],
            'description' => ['nullable', 'string'],
        ];
    }
}
