<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => $this->amount,
            'note' => $this->note,
            'photo_url' => $this->photo_path
                ? asset('storage/'.$this->photo_path)
                : null,
            'wallet' => new WalletResource($this->whenLoaded('wallet')),
            'transaction_category' => new TransactionCategoryResource($this->whenLoaded('transactionCategory')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
