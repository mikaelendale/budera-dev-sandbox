<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->json('abilities')->nullable()->after('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropColumn('abilities');
        });
    }
};
