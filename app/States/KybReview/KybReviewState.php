<?php

namespace App\States\KybReview;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class KybReviewState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(KybReviewPending::class)
            ->registerState([
                KybReviewPending::class,
                KybReviewUnderReview::class,
                KybReviewApproved::class,
                KybReviewRejected::class,
            ])
            ->allowTransition(KybReviewPending::class, KybReviewUnderReview::class)
            ->allowTransition(KybReviewUnderReview::class, KybReviewApproved::class)
            ->allowTransition(KybReviewUnderReview::class, KybReviewRejected::class);
    }
}

class KybReviewPending extends KybReviewState
{
    protected static string $name = 'pending';
}

class KybReviewUnderReview extends KybReviewState
{
    protected static string $name = 'under_review';
}

class KybReviewApproved extends KybReviewState
{
    protected static string $name = 'approved';
}

class KybReviewRejected extends KybReviewState
{
    protected static string $name = 'rejected';
}
