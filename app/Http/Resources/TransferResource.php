<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
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
            'amount' => $this->amount,
            'fee' => $this->fee,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'note' => $this->note,
            'from_wallet' => new WalletResource($this->whenLoaded('fromWallet')),
            'to_wallet' => new WalletResource($this->whenLoaded('toWallet')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
