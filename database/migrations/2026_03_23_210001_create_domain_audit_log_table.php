<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('stream');
            $table->string('actor_type');
            $table->string('actor_id')->nullable()->index();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable()->index();
            $table->string('environment')->nullable()->index();
            $table->json('metadata');
            $table->string('correlation_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_audit_log');
    }
};
