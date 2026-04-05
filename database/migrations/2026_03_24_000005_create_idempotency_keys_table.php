<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 255);
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
