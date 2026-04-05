# End-to-end smoke of mock-bank HTTP API.
# Start mock-bank + Laravel yourself. Webhooks need Laravel reachable at BUDERA_WEBHOOK_URL in mock-bank/.env.local
#
# How to run (do NOT double-click the .ps1 — Windows may open Notepad):
#   PowerShell:  cd <repo> ; powershell -ExecutionPolicy Bypass -File .\scripts\test-mock-bank-e2e.ps1
#   CMD:         cd <repo> && scripts\run-mock-bank-e2e.cmd
#   Or double-click: scripts\run-mock-bank-e2e.cmd
$ErrorActionPreference = "Stop"
$Base = if ($env:MOCK_BANK_BASE_URL) { $env:MOCK_BANK_BASE_URL.TrimEnd('/') } else { "http://127.0.0.1:3000" }
$Secret = if ($env:MOCK_BANK_SECRET) { $env:MOCK_BANK_SECRET } else { "local_dev_shared_secret_budera_mock_2026" }
Write-Host "Using mock base: $Base" -ForegroundColor DarkGray
$Headers = @{
    "X-Bank-Secret" = $Secret
    "Content-Type"  = "application/json"
}

function Invoke-JsonPost {
    param([string]$Uri, [string]$Body)
    return Invoke-RestMethod -Method POST -Uri $Uri -Headers $Headers -Body $Body
}

function Invoke-JsonGet {
    param([string]$Uri)
    return Invoke-RestMethod -Method GET -Uri $Uri -Headers $Headers
}

Write-Host "== GET /health (no secret) ==" -ForegroundColor Cyan
$h = Invoke-RestMethod -Method GET -Uri "$Base/health"
if (-not $h.ok) { throw "health failed" }
Write-Host "OK" $h

Write-Host "`n== POST /api/accounts ==" -ForegroundColor Cyan
$acc = Invoke-JsonPost "$Base/api/accounts" '{"currency":"USD"}'
$aid = $acc.id
Write-Host "account id:" $aid

Write-Host "`n== POST /api/transfers/ach credit (fund) ==" -ForegroundColor Cyan
$ach = Invoke-JsonPost "$Base/api/transfers/ach" (@{
        direction    = "credit"
        account_id   = $aid
        amount_cents = 50000
    } | ConvertTo-Json)
$trfAch = $ach.transfer_id
Write-Host "transfer_id:" $trfAch

Write-Host "`n== GET /api/accounts/{id}/balance ==" -ForegroundColor Cyan
$bal = Invoke-JsonGet "$Base/api/accounts/$aid/balance"
Write-Host "balance_cents:" $bal.balance_cents

Write-Host "`n== POST /api/transfers/wire ==" -ForegroundColor Cyan
Invoke-JsonPost "$Base/api/transfers/wire" (@{
        account_id   = $aid
        amount_cents = 100
        beneficiary  = @{ routing_number = "021000021"; account_number = "123456789"; name = "Bob" }
    } | ConvertTo-Json) | Out-Null
Write-Host "OK"

Write-Host "`n== POST /api/transfers/swift ==" -ForegroundColor Cyan
Invoke-JsonPost "$Base/api/transfers/swift" (@{
        account_id   = $aid
        amount_cents = 200
        currency     = "USD"
        bic          = "CHASUS33"
    } | ConvertTo-Json) | Out-Null
Write-Host "OK"

Write-Host "`n== POST /api/transfers/fednow ==" -ForegroundColor Cyan
Invoke-JsonPost "$Base/api/transfers/fednow" (@{
        account_id   = $aid
        amount_cents = 50
        direction    = "send"
    } | ConvertTo-Json) | Out-Null
Write-Host "OK"

Write-Host "`n== POST /api/accounts (second for book) ==" -ForegroundColor Cyan
$acc2 = Invoke-JsonPost "$Base/api/accounts" '{"currency":"USD"}'
$aid2 = $acc2.id
Invoke-JsonPost "$Base/api/transfers/ach" (@{
        direction    = "credit"
        account_id   = $aid2
        amount_cents = 10000
    } | ConvertTo-Json) | Out-Null

Write-Host "`n== POST /api/transfers/book ==" -ForegroundColor Cyan
Invoke-JsonPost "$Base/api/transfers/book" (@{
        from_account_id = $aid
        to_account_id   = $aid2
        amount_cents    = 100
    } | ConvertTo-Json) | Out-Null
Write-Host "OK"

Write-Host "`n== POST /api/transfers/check issue ==" -ForegroundColor Cyan
$chk = Invoke-JsonPost "$Base/api/transfers/check" (@{
        account_id   = $aid
        amount_cents = 100
        payee        = "Acme Corp"
        memo         = "test"
    } | ConvertTo-Json)
Write-Host "check transfer_id:" $chk.transfer_id

Write-Host "`n== POST /api/kyc/submissions ==" -ForegroundColor Cyan
$kyc = Invoke-JsonPost "$Base/api/kyc/submissions" (@{
        account_id    = $aid
        legal_name    = "Jane Test"
        last4_ssn     = "1234"
    } | ConvertTo-Json)
Write-Host "kyc id:" $kyc.id

Write-Host "`n== GET /api/transfers/{id} ==" -ForegroundColor Cyan
$t = Invoke-JsonGet "$Base/api/transfers/$trfAch"
Write-Host "rail:" $t.rail "status:" $t.status

Write-Host "`n== POST /api/ach/push (legacy) ==" -ForegroundColor Cyan
$leg = Invoke-JsonPost "$Base/api/ach/push" (@{
        account_id   = $aid
        amount_cents = 1
    } | ConvertTo-Json)
Write-Host "ref:" $leg.ref

Write-Host "`n== GET /api/kyc/submissions/{id} (poll; mock verifies async) ==" -ForegroundColor Cyan
$deadline = (Get-Date).AddSeconds(6)
$ks = $null
do {
    $ks = Invoke-JsonGet "$Base/api/kyc/submissions/$($kyc.id)"
    if ($ks.status -ne 'pending') { break }
    Start-Sleep -Milliseconds 250
} while ((Get-Date) -lt $deadline)
Write-Host "kyc status:" $ks.status

Write-Host "`nAll mock-bank endpoint calls completed." -ForegroundColor Green
