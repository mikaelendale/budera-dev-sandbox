<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerBankIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'label',
        'environment',
        'base_url',
        'credentials',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public static function maskPreview(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $len = strlen($value);
        if ($len <= 4) {
            return '••••';
        }

        return '••••'.substr($value, -4);
    }

    /**
     * @return array<string, mixed>
     */
    public function safeForInertia(): array
    {
        $c = is_array($this->credentials) ? $this->credentials : [];

        $out = $c['outbound_api_secret'] ?? null;
        $in = $c['inbound_webhook_secret'] ?? null;

        return [
            'id' => $this->getKey(),
            'provider' => $this->provider,
            'label' => $this->label,
            'environment' => $this->environment,
            'base_url' => $this->base_url,
            'is_active' => $this->is_active,
            'has_outbound_secret' => is_string($out) && $out !== '',
            'has_inbound_webhook_secret' => is_string($in) && $in !== '',
            'outbound_secret_preview' => self::maskPreview(is_string($out) ? $out : null),
            'inbound_webhook_secret_preview' => self::maskPreview(is_string($in) ? $in : null),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
