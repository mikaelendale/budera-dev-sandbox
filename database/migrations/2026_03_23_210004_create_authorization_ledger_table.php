<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('stream')->index();
            $table->string('actor_type');
            $table->string('actor_id')->nullable()->index();
            $table->string('authorization_text');
            $table->string('authorization_hash', 64)->index();
            $table->text('authorization_signature');
            $table->string('correlation_id')->nullable()->index();
            $table->string('environment')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_ledger');
    }
};
