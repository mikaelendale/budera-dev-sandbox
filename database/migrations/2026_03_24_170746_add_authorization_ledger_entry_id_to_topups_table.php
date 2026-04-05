<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topups', function (Blueprint $table): void {
            $table->foreignId('authorization_ledger_entry_id')
                ->nullable()
                ->after('bank_link_id')
                ->constrained('authorization_ledger')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('topups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('authorization_ledger_entry_id');
        });
    }
};
