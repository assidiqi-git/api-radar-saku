<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTransactionRequest extends FormRequest
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
        $userId = $this->user()->id;

        return [
            'transactions' => ['required', 'array', 'min:1', 'max:500'],

            'transactions.*.id' => [
                'required',
                'string',
                'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/i',
            ],

            'transactions.*.wallet_id' => [
                'required',
                'string',
                Rule::exists('wallets', 'id')->where('user_id', $userId),
            ],

            'transactions.*.transaction_category_id' => [
                'required',
                'string',
                Rule::exists('transaction_categories', 'id')->where('user_id', $userId),
            ],

            'transactions.*.amount' => ['required', 'numeric', 'min:0.01'],

            'transactions.*.name' => ['required', 'string', 'max:255'],

            'transactions.*.note' => ['nullable', 'string'],

            'transactions.*.created_at' => ['nullable', 'date'],

            'transactions.*.deleted_at' => ['nullable', 'date'],
        ];
    }
}
