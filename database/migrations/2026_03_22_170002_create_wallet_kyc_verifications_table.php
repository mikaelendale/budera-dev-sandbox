<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->string('status');
            $table->string('mock_kyc_submission_id')->nullable()->index();
            $table->json('submitted_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_kyc_verifications');
    }
};
