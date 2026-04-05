<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensures companies has KYB/profile columns when an older DB ran migrations
     * before 2026_03_24_100000_backfill_phase_two_missing_columns.
     */
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $needsAlter = ! Schema::hasColumn('companies', 'email')
            || ! Schema::hasColumn('companies', 'kyb_status')
            || ! Schema::hasColumn('companies', 'sandbox_limit_overrides');

        if (! $needsAlter) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
            if (! Schema::hasColumn('companies', 'kyb_status')) {
                $table->string('kyb_status')->default('not_started')->after('owner_id');
            }
            if (! Schema::hasColumn('companies', 'sandbox_limit_overrides')) {
                if (Schema::hasColumn('companies', 'live_enabled_at')) {
                    $table->json('sandbox_limit_overrides')->nullable()->after('live_enabled_at');
                } else {
                    $table->json('sandbox_limit_overrides')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'sandbox_limit_overrides')) {
                $table->dropColumn('sandbox_limit_overrides');
            }
            if (Schema::hasColumn('companies', 'kyb_status')) {
                $table->dropColumn('kyb_status');
            }
            if (Schema::hasColumn('companies', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
