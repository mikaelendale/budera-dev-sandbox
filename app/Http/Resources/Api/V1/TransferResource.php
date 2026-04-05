<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'from_wallet_account_id' => $this->fromWalletAccount?->public_id,
            'to_wallet_account_id' => $this->toWalletAccount?->public_id,
            'environment' => $this->environment,
            'status' => $this->status->getValue(),
            'amount_usd' => $this->amount_usd !== null ? (float) $this->amount_usd : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
