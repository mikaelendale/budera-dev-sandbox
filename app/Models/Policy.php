<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Policy extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_account_id',
        'agent_type',
        'per_tx_limit_usd',
        'daily_spend_limit_usd',
        'daily_tx_count',
        'allowed_categories',
        'blocked_payees',
        'require_approval_above',
        'approval_timeout_secs',
        'max_new_payees_per_day',
        'business_hours_only',
        'velocity_sensitivity',
        'auto_topup',
    ];

    protected function casts(): array
    {
        return [
            'allowed_categories' => 'array',
            'blocked_payees' => 'array',
            'auto_topup' => 'array',
            'business_hours_only' => 'boolean',
        ];
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
    }
}
