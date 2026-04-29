<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
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
            'from_wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')->where('user_id', $this->user()->id),
            ],
            'to_wallet_id' => [
                'required',
                'integer',
                'different:from_wallet_id',
                Rule::exists('wallets', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }
}
