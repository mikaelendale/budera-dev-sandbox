<?php

namespace App\Services;

use App\Models\Company;
use App\Models\KybReview;
use App\Models\User;
use App\Notifications\Transactional\KybApprovedNotification;
use App\Notifications\Transactional\KybRejectedNotification;
use App\Services\Audit\TransitionRecorder;
use App\Services\Mail\TransactionalMail;
use App\States\KybReview\KybReviewApproved;
use App\States\KybReview\KybReviewPending;
use App\States\KybReview\KybReviewRejected;
use App\States\KybReview\KybReviewUnderReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KybService
{
    public function __construct(
        private readonly TransitionRecorder $transitionRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $questionnaire
     */
    public function submitForReview(
        Company $company,
        array $questionnaire,
        ?UploadedFile $governmentId = null,
        ?UploadedFile $certificateIncorporation = null,
        ?UploadedFile $directorId = null,
    ): KybReview {
        if ($company->live_enabled_at !== null) {
            throw new InvalidArgumentException('company_already_live_enabled');
        }

        $hasOpen = KybReview::query()
            ->where('company_id', $company->getKey())
            ->where(function ($q): void {
                $q->where('status', KybReviewPending::class)
                    ->orWhere('status', KybReviewUnderReview::class);
            })
            ->exists();

        if ($hasOpen) {
            throw new InvalidArgumentException('kyb_review_already_open');
        }

        return DB::transaction(function () use ($company, $questionnaire, $governmentId, $certificateIncorporation, $directorId): KybReview {
            $company->kyb_status = 'pending';
            $company->save();

            $review = KybReview::query()->create([
                'company_id' => $company->getKey(),
                'environment' => 'live',
                'status' => KybReviewPending::class,
                'documents' => [],
                'questionnaire' => $questionnaire,
                'notes' => null,
            ]);

            $documents = [];

            if ($governmentId !== null) {
                $path = $governmentId->store("kyb/{$review->getKey()}", 'local');
                $documents['government_id'] = [
                    'path' => $path,
                    'original_name' => $governmentId->getClientOriginalName(),
                ];
            }

            if ($certificateIncorporation !== null) {
                $path = $certificateIncorporation->store("kyb/{$review->getKey()}", 'local');
                $documents['certificate_of_incorporation'] = [
                    'path' => $path,
                    'original_name' => $certificateIncorporation->getClientOriginalName(),
                ];
            }

            if ($directorId !== null) {
                $path = $directorId->store("kyb/{$review->getKey()}", 'local');
                $documents['director_id'] = [
                    'path' => $path,
                    'original_name' => $directorId->getClientOriginalName(),
                ];
            }

            if ($documents !== []) {
                $review->forceFill(['documents' => $documents])->save();
            }

            return $review->fresh();
        });
    }

    public function startReview(KybReview $review, User $admin): KybReview
    {
        if (! $review->status instanceof KybReviewPending) {
            throw new InvalidArgumentException('kyb_review_not_pending');
        }

        return DB::transaction(function () use ($review, $admin): KybReview {
            $review->status->transitionTo(KybReviewUnderReview::class);
            $review->save();

            $company = $review->company()->firstOrFail();
            $company->kyb_status = 'under_review';
            $company->save();

            $this->transitionRecorder->record(
                $review,
                'pending',
                'under_review',
                [
                    'stream' => 'internal_admin',
                    'actor_type' => 'user',
                    'actor_id' => (string) $admin->getKey(),
                    'action' => 'kyb.review.started',
                    'resource_type' => 'kyb_reviews',
                    'resource_id' => (string) $review->getKey(),
                    'environment' => $review->environment,
                    'metadata' => [
                        'company_id' => (string) $review->company_id,
                        'admin_user_id' => (string) $admin->getKey(),
                    ],
                ],
            );

            return $review->fresh();
        });
    }

    public function approve(KybReview $review, User $admin): KybReview
    {
        if (! $review->status instanceof KybReviewUnderReview) {
            throw new InvalidArgumentException('kyb_review_not_under_review');
        }

        return DB::transaction(function () use ($review, $admin): KybReview {
            $review->status->transitionTo(KybReviewApproved::class);
            $review->decided_at = now();
            $review->notes = null;
            $review->save();

            $company = $review->company()->firstOrFail();
            $company->kyb_status = 'approved';
            $company->save();

            $this->transitionRecorder->record(
                $review,
                'under_review',
                'approved',
                [
                    'stream' => 'internal_admin',
                    'actor_type' => 'user',
                    'actor_id' => (string) $admin->getKey(),
                    'action' => 'kyb.review.approved',
                    'resource_type' => 'kyb_reviews',
                    'resource_id' => (string) $review->getKey(),
                    'environment' => $review->environment,
                    'metadata' => [
                        'company_id' => (string) $review->company_id,
                        'admin_user_id' => (string) $admin->getKey(),
                    ],
                    'webhook_event' => 'kyb.approved',
                    'webhook_payload' => [
                        'event' => 'kyb.approved',
                        'data' => [
                            'company_id' => (string) $company->getKey(),
                            'kyb_review_id' => (string) $review->getKey(),
                        ],
                    ],
                ],
            );

            $owner = $company->owner;
            TransactionalMail::notifyUser($owner, new KybApprovedNotification($company, $review));

            return $review->fresh();
        });
    }

    public function reject(KybReview $review, User $admin, string $reason): KybReview
    {
        if (! $review->status instanceof KybReviewUnderReview) {
            throw new InvalidArgumentException('kyb_review_not_under_review');
        }

        return DB::transaction(function () use ($review, $admin, $reason): KybReview {
            $review->status->transitionTo(KybReviewRejected::class);
            $review->decided_at = now();
            $review->notes = $reason;
            $review->save();

            $company = $review->company()->firstOrFail();
            $company->kyb_status = 'rejected';
            $company->save();

            $this->transitionRecorder->record(
                $review,
                'under_review',
                'rejected',
                [
                    'stream' => 'internal_admin',
                    'actor_type' => 'user',
                    'actor_id' => (string) $admin->getKey(),
                    'action' => 'kyb.review.rejected',
                    'resource_type' => 'kyb_reviews',
                    'resource_id' => (string) $review->getKey(),
                    'environment' => $review->environment,
                    'metadata' => [
                        'company_id' => (string) $review->company_id,
                        'admin_user_id' => (string) $admin->getKey(),
                        'reason' => $reason,
                    ],
                ],
            );

            $owner = $company->owner;
            TransactionalMail::notifyUser($owner, new KybRejectedNotification($company, $review, $reason));

            return $review->fresh();
        });
    }
}
