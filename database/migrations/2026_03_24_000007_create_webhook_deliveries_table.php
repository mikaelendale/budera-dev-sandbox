<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event');
            $table->string('event_id');
            $table->json('payload');
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'status']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
