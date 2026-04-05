<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_kyc_verifications', function (Blueprint $table) {
            $table->string('session_token')->nullable()->unique()->after('status');
            $table->timestamp('session_expires_at')->nullable()->after('session_token');
            $table->string('hosted_url')->nullable()->after('session_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_kyc_verifications', function (Blueprint $table) {
            $table->dropUnique(['session_token']);
            $table->dropColumn(['session_token', 'session_expires_at', 'hosted_url']);
        });
    }
};
