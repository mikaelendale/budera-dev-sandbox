<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Topup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Topup
 */
class TopupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'wallet_account_id' => $this->walletAccount?->public_id,
            'bank_link_id' => $this->bankLink?->public_id,
            'environment' => $this->environment,
            'status' => $this->status->getValue(),
            'amount_usd' => $this->amount_usd !== null ? (float) $this->amount_usd : null,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
