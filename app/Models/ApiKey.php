<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\Concerns\HasPublicId;
use App\States\ApiKey\ApiKeyState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

class ApiKey extends Model
{
    use BelongsToCompany, HasFactory, HasPublicId, HasStates;

    public static function publicIdPrefix(): string
    {
        return 'key_';
    }

    protected $fillable = [
        'company_id',
        'owner_id',
        'environment',
        'status',
        'key_hash',
        'label',
        'abilities',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApiKeyState::class,
            'abilities' => 'array',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = is_array($this->abilities) ? $this->abilities : [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
