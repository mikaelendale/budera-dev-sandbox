<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->decimal('per_tx_limit_usd', 18, 2)->nullable();
            $table->decimal('daily_spend_limit_usd', 18, 2)->nullable();
            $table->unsignedInteger('daily_tx_count')->nullable();
            $table->json('allowed_categories')->nullable();
            $table->json('blocked_payees')->nullable();
            $table->decimal('require_approval_above', 18, 2)->nullable();
            $table->unsignedInteger('approval_timeout_secs')->nullable();
            $table->unsignedInteger('max_new_payees_per_day')->nullable();
            $table->boolean('business_hours_only')->default(false);
            $table->enum('velocity_sensitivity', ['low', 'medium', 'high'])->default('medium');
            $table->json('auto_topup')->nullable();
            $table->timestamps();

            $table->unique('wallet_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
