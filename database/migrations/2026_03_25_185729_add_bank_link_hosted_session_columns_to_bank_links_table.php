<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_links', function (Blueprint $table): void {
            $table->string('session_token_hash', 64)->nullable()->unique()->after('user_id');
            $table->timestamp('session_expires_at')->nullable()->after('session_token_hash');
            $table->foreignId('company_id')->nullable()->after('environment')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_links', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['session_token_hash', 'session_expires_at', 'company_id']);
        });
    }
};
