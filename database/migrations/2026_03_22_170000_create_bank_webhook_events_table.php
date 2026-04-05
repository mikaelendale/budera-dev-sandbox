<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->json('payload');
            $table->string('transfer_id')->nullable()->index();
            $table->string('mock_kyc_submission_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_webhook_events');
    }
};
