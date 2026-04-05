<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->string('event_id')->unique();
            $table->string('environment')->nullable()->index();
            $table->json('payload');
            $table->string('destination_url')->nullable();
            $table->string('destination_key')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('status')->default('queued')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('reserved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_outbox');
    }
};
