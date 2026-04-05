<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bank_connections');

        Schema::create('partner_bank_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('label');
            $table->string('environment', 16);
            $table->string('base_url')->nullable();
            /** @var array<string, mixed> Encrypted JSON: outbound_api_secret, inbound_webhook_secret */
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_bank_integrations');

        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 64);
            $table->string('environment', 16)->default('sandbox');
            $table->string('base_url')->nullable();
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'provider']);
        });
    }
};
