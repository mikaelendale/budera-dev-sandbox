<?php

namespace App\Console\Commands;

use App\Models\AuthorizationLedgerEntry;
use App\Services\Audit\CryptoSigner;
use Illuminate\Console\Command;

class ExportAuthorizationLedgerCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'authorization-ledger:export {entry_id : Primary key of the authorization_ledger row}';

    /**
     * @var string
     */
    protected $description = 'Output a deterministic JSON bundle for an authorization ledger entry (signature verification metadata included).';

    public function handle(CryptoSigner $cryptoSigner): int
    {
        $id = (int) $this->argument('entry_id');
        if ($id < 1) {
            $this->error('Invalid entry_id.');

            return self::FAILURE;
        }

        /** @var AuthorizationLedgerEntry|null $entry */
        $entry = AuthorizationLedgerEntry::query()->find($id);

        if ($entry === null) {
            $this->error('Authorization ledger entry not found.');

            return self::FAILURE;
        }

        $text = (string) $entry->authorization_text;
        $sig = (string) $entry->authorization_signature;

        $bundle = [
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'entry' => [
                'id' => $entry->getKey(),
                'stream' => $entry->stream,
                'actor_type' => $entry->actor_type,
                'actor_id' => $entry->actor_id,
                'authorization_hash' => $entry->authorization_hash,
                'authorization_signature' => $sig,
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
                'account_id' => $entry->account_id,
                'correlation_id' => $entry->correlation_id,
                'environment' => $entry->environment,
                'metadata' => $entry->metadata,
                'created_at' => $entry->created_at?->toIso8601String(),
            ],
            'canonical_payload' => $text,
            'canonical_payload_sha256' => hash('sha256', $text),
            'stored_authorization_hash' => $entry->authorization_hash,
            'signature_valid' => $cryptoSigner->verifySignature($text, $sig),
        ];

        $this->line(json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
