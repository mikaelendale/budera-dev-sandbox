<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->text('secret');
            $table->json('events')->nullable();
            $table->string('environment', 16)->default('sandbox');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
