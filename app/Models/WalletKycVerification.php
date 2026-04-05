<?php

namespace App\Models;

use App\States\WalletKycVerification\WalletKycVerificationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

class WalletKycVerification extends Model
{
    use HasStates;

    protected $fillable = [
        'wallet_account_id',
        'status',
        'session_token',
        'session_expires_at',
        'hosted_url',
        'mock_kyc_submission_id',
        'submitted_payload',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WalletKycVerificationState::class,
            'session_expires_at' => 'datetime',
            'submitted_payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
    }
}
