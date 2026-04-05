<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use App\States\KybReview\KybReviewState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

class KybReview extends Model
{
    use BelongsToCompany, HasFactory, HasStates;

    protected $fillable = [
        'company_id',
        'environment',
        'status',
        'decided_at',
        'notes',
        'documents',
        'questionnaire',
    ];

    protected function casts(): array
    {
        return [
            'status' => KybReviewState::class,
            'documents' => 'array',
            'questionnaire' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
