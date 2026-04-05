<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ComplianceFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'flaggable_type',
        'flaggable_id',
        'flag_type',
        'severity',
        'details',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
