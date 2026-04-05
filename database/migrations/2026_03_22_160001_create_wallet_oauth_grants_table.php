<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_oauth_grants', function (Blueprint $table) {
            $table->id();
            $table->string('oauth_access_token_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('oauth_client_id')->nullable()->constrained('oauth_clients')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('wallet_account_id')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique('oauth_access_token_id');
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_oauth_grants');
    }
};
