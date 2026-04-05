<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BankLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankLink
 */
class BankLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $meta = is_array($this->metadata) ? $this->metadata : [];
        $doc = $meta['sandbox_microdeposit_documentation'] ?? null;

        $out = [
            'id' => $this->public_id,
            'environment' => $this->environment,
            'status' => $this->status->getValue(),
            'bank_slug' => $this->bank_slug,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if (is_string($doc) && $doc !== '' && $this->environment === 'sandbox') {
            $out['sandbox_microdeposit_documentation'] = $doc;
        }

        return $out;
    }
}
