<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'wallet_account_id' => $this->walletAccount?->public_id,
            'environment' => $this->environment,
            'status' => $this->status->getValue(),
            'direction' => $this->direction,
            'rail' => $this->rail,
            'payee_ref' => $this->payee_ref,
            'amount_usd' => $this->amount_usd !== null ? (float) $this->amount_usd : null,
            'held_reason' => $this->held_reason,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
