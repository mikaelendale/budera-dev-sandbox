<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_account_id')->constrained('wallet_accounts')->cascadeOnDelete();
            $table->string('environment')->default('sandbox');
            $table->string('status');
            $table->decimal('amount_usd', 18, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topups');
    }
};
