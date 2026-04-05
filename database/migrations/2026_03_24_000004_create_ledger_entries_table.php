<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->enum('type', ['debit', 'credit']);
            $table->bigInteger('amount_cents');
            $table->string('reference_type', 64);
            $table->uuid('reference_id');
            $table->bigInteger('balance_after_cents');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_account_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
