<?php

namespace App\Console\Commands;

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssuePassportTokenCommand extends Command
{
    protected $signature = 'budera:token
                            {email : Email of the user who will own the token}
                            {--scopes=wallet:read : Comma-separated Passport scopes (e.g. wallet:read,wallet:pay)}
                            {--plain : Output only the raw token string (for scripting / shell capture)}
                            {--client-id= : Optional: your OAuth app client_id (UUID) to print a browser /oauth/authorize URL}
                            {--redirect-uri= : Required with --client-id; must match the redirect URI registered for that client}';

    protected $description = 'Issue a Passport personal access token (mimics API access without the browser OAuth redirect)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to run: set APP_ENV=local (or testing) for development-only token issuance.');

            return self::FAILURE;
        }

        $this->ensurePersonalAccessClientExists();

        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error("No user found with email: {$email}");

            return self::FAILURE;
        }

        $scopeString = (string) $this->option('scopes');
        $scopes = array_values(array_filter(array_map('trim', explode(',', $scopeString))));
        if ($scopes === []) {
            $this->error('Provide at least one scope.');

            return self::FAILURE;
        }

        $unknown = array_diff($scopes, array_keys(config('budera.oauth.token_scopes', [])));
        if ($unknown !== []) {
            $this->warn('Unknown scope id(s): '.implode(', ', $unknown).' (proceeding anyway)');
        }

        $clientId = $this->option('client-id');
        $redirectUri = $this->option('redirect-uri');
        if (is_string($clientId) && $clientId !== '' && (! is_string($redirectUri) || $redirectUri === '')) {
            $this->error('Pass --redirect-uri=... together with --client-id (must match the OAuth app).');

            return self::FAILURE;
        }

        $token = $user->createToken('budera-cli', $scopes)->accessToken;

        if ($this->option('plain')) {
            $this->output->write($token);

            return self::SUCCESS;
        }

        $base = rtrim((string) config('app.url'), '/');
        $scopesCsv = implode(',', $scopes);

        $this->newLine();
        $this->line('<fg=green>Personal access token (Bearer for /api/v1/* — do not send to POST /oauth/token)</>');
        $this->line($token);
        $this->newLine();

        $this->components->info('Quick use (avoids copy-paste line-wrap issues)');
        $this->line('<fg=yellow>  # Bash / Git Bash:</>');
        $this->line('<fg=cyan>  OAUTH_TOKEN=$(php artisan budera:token '.$email.' --scopes='.$scopesCsv.' --plain)</>');
        $this->line('<fg=cyan>  curl -sS "$BASE/api/v1/wallet/me" -H "Authorization: Bearer $OAUTH_TOKEN" -H "Accept: application/json" | json_pp</>');
        $this->newLine();
        $this->line('<fg=yellow>  # PowerShell:</>');
        $this->line('<fg=cyan>  $OAUTH_TOKEN = php artisan budera:token '.$email.' --scopes='.$scopesCsv.' --plain</>');
        $this->line('<fg=cyan>  curl -sS "$BASE/api/v1/wallet/me" -H "Authorization: Bearer $OAUTH_TOKEN" -H "Accept: application/json" | json_pp</>');
        $this->newLine();

        $this->components->info('End users & passwords');
        $this->line('There is no separate “OAuth password”. The end user is a normal Budera account: same <fg=cyan>email + password</> as logging into <fg=cyan>'.$base.'/login</> (the <fg=cyan>users</> table).');
        $this->line('Your product sends them to <fg=cyan>/oauth/authorize</>; they sign in if needed, approve, then your <fg=cyan>redirect_uri</> receives <fg=cyan>?code=...</> for <fg=cyan>POST /oauth/token</>.');
        $this->newLine();
        $this->line('This command <fg=yellow>skips</> that browser flow and mints a dev token for the user above — use when you have no embedded login yet.');
        $this->newLine();
        $this->line('For <fg=yellow>company integrations</> without any human (agents, servers), prefer <fg=cyan>API keys</> (<fg=cyan>sk_sandbox_...</>) instead of OAuth.');
        $this->newLine();

        if (is_string($clientId) && $clientId !== '' && is_string($redirectUri) && $redirectUri !== '') {
            $scopeParam = implode(' ', $scopes);
            $authorizeUrl = $base.'/oauth/authorize?'.http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => $scopeParam,
                'state' => 'dev',
            ]);

            $this->components->info('Browser OAuth (real authorization code)');
            $this->line('Log in as <fg=cyan>'.$email.'</> (or any user) at <fg=cyan>'.$base.'/login</>, then open:');
            $this->line($authorizeUrl);
            $this->line('After approval, copy <fg=cyan>code</> from the redirect and exchange it at <fg=cyan>POST /oauth/token</> with the same <fg=cyan>redirect_uri</> and <fg=cyan>client_secret</>.');
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function ensurePersonalAccessClientExists(): void
    {
        $hasPersonal = OAuthClient::query()
            ->where('revoked', false)
            ->get()
            ->contains(fn (OAuthClient $c): bool => $c->hasGrantType('personal_access'));

        if ($hasPersonal) {
            return;
        }

        OAuthClient::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Personal Access Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);

        $this->info('Created Personal Access OAuth client (one-time setup).');
    }
}
