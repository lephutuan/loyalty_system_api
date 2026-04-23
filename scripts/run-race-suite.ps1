param(
    [int]$Rounds = 10,
    [string]$ProjectRoot = "D:/Magento_Drupal/task_week_1",
    [string]$BaseUrl = "http://localhost:8000/api/v1"
)

$ErrorActionPreference = "Stop"
Set-Location $ProjectRoot

Write-Host "[1/4] Applying migrations..."
docker compose exec app php bin/console doctrine:migrations:migrate -n | Out-Host

Write-Host "[2/4] Running redeem race loop..."
$env:BASE_URL = $BaseUrl
$env:ROUNDS = "$Rounds"
$env:RESEED_COMMAND = "docker compose exec app php bin/console app:seed:loyalty --reset"
node scripts/race-load-test.js redeem-race-loop
if ($LASTEXITCODE -ne 0) {
    throw "Redeem race loop failed."
}

Write-Host "[3/4] Running transaction burst load..."
$env:CONCURRENCY = "20"
$env:ROUNDS = "5"
node scripts/race-load-test.js transaction-burst
if ($LASTEXITCODE -ne 0) {
    throw "Transaction burst failed."
}

Write-Host "[4/4] Verifying DB invariants..."
$ledgerMismatch = docker compose exec -T mysql mysql -uapp -papp -D loyalty -Nse "SELECT COUNT(*) FROM (SELECT w.id FROM wallets w LEFT JOIN points p ON p.wallet_id = w.id GROUP BY w.id, w.balance HAVING w.balance <> COALESCE(SUM(p.point_amount), 0)) t;"
$negativeStock = docker compose exec -T mysql mysql -uapp -papp -D loyalty -Nse "SELECT COUNT(*) FROM gifts WHERE stock < 0;"
$negativeBalance = docker compose exec -T mysql mysql -uapp -papp -D loyalty -Nse "SELECT COUNT(*) FROM wallets WHERE balance < 0;"

Write-Host "ledger_mismatch_count=$ledgerMismatch"
Write-Host "negative_stock_count=$negativeStock"
Write-Host "negative_balance_count=$negativeBalance"

if ($ledgerMismatch -ne "0" -or $negativeStock -ne "0" -or $negativeBalance -ne "0") {
    throw "Invariant check failed."
}

Write-Host "PASS: race suite completed with all invariants satisfied."
