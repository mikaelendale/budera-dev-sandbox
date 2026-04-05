<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount_cents' => (int) $this->amount_cents,
            'reference_type' => $this->reference_type,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
