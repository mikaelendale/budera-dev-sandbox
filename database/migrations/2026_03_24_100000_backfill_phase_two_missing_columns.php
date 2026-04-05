<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsCompanyColumns = ! Schema::hasColumn('companies', 'email')
            || ! Schema::hasColumn('companies', 'kyb_status')
            || ! Schema::hasColumn('companies', 'sandbox_limit_overrides');

        if ($needsCompanyColumns) {
            Schema::table('companies', function (Blueprint $table): void {
                if (! Schema::hasColumn('companies', 'email')) {
                    $table->string('email')->nullable()->after('name');
                }
                if (! Schema::hasColumn('companies', 'kyb_status')) {
                    $table->string('kyb_status')->default('not_started')->after('owner_id');
                }
                if (! Schema::hasColumn('companies', 'sandbox_limit_overrides')) {
                    $table->json('sandbox_limit_overrides')->nullable()->after('live_enabled_at');
                }
            });
        }

        Schema::table('wallet_accounts', function (Blueprint $table): void {
            $table->string('agent_id')->nullable()->index()->after('user_id');
            $table->bigInteger('balance_cents')->default(0)->after('partner_account_id');
            $table->string('public_id')->nullable()->unique()->after('id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->string('direction')->default('outbound')->after('status');
            $table->string('rail')->nullable()->after('direction');
            $table->string('payee_ref')->nullable()->after('rail');
            $table->string('idempotency_key')->nullable()->index()->after('payee_ref');
            $table->string('held_reason')->nullable()->after('metadata');
            $table->timestamp('settled_at')->nullable()->after('held_reason');
        });

        Schema::table('topups', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->foreignId('bank_link_id')->nullable()->after('wallet_account_id')
                ->constrained('bank_links')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->index()->after('amount_usd');
            $table->timestamp('settled_at')->nullable()->after('metadata');
        });

        Schema::table('transfers', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->string('idempotency_key')->nullable()->index()->after('amount_usd');
        });

        Schema::table('bank_links', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->text('encrypted_routing')->nullable()->after('routing_hash');
            $table->text('encrypted_account')->nullable()->after('encrypted_routing');
            $table->unsignedTinyInteger('failed_verification_attempts')->default(0)->after('revoked_at');
        });

        Schema::table('authorization_ledger', function (Blueprint $table): void {
            $table->string('ip_address')->nullable()->after('authorization_signature');
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->unsignedBigInteger('account_id')->nullable()->index()->after('user_agent');
        });

        Schema::table('webhook_outbox', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('policies', function (Blueprint $table): void {
            $table->string('agent_type')->nullable()->after('wallet_account_id');
        });

        Schema::table('api_keys', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
            $table->string('label')->nullable()->after('key_hash');
            $table->foreignId('owner_id')->nullable()->after('company_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['public_id', 'label', 'owner_id']);
        });

        Schema::table('policies', function (Blueprint $table): void {
            $table->dropColumn('agent_type');
        });

        Schema::table('webhook_outbox', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('authorization_ledger', function (Blueprint $table): void {
            $table->dropColumn(['ip_address', 'user_agent', 'account_id']);
        });

        Schema::table('bank_links', function (Blueprint $table): void {
            $table->dropColumn(['public_id', 'encrypted_routing', 'encrypted_account', 'failed_verification_attempts']);
        });

        Schema::table('transfers', function (Blueprint $table): void {
            $table->dropColumn(['public_id', 'idempotency_key']);
        });

        Schema::table('topups', function (Blueprint $table): void {
            $table->dropForeign(['bank_link_id']);
            $table->dropColumn(['public_id', 'bank_link_id', 'idempotency_key', 'settled_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['public_id', 'direction', 'rail', 'payee_ref', 'idempotency_key', 'held_reason', 'settled_at']);
        });

        Schema::table('wallet_accounts', function (Blueprint $table): void {
            $table->dropColumn(['agent_id', 'balance_cents', 'public_id']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['email', 'kyb_status', 'sandbox_limit_overrides']);
        });
    }
};
