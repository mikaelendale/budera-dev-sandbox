<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
        });

        Schema::table('bank_links', function (Blueprint $table) {
            $table->foreignId('wallet_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('wallet_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_links', function (Blueprint $table) {
            $table->dropForeign(['wallet_account_id']);
            $table->dropColumn('wallet_account_id');
        });

        Schema::table('wallet_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
        });
    }
};
