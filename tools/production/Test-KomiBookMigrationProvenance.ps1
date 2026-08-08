[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $CandidateBackend,

    [ValidateNotNullOrEmpty()]
    [string] $PhpExecutable
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# These hashes are the production-recorded migration provenance baseline as of
# 2026-08-08. Do not update them to make a candidate pass; investigate any drift.
$requiredMigrations = @(
    [pscustomobject]@{
        Name = '2026_08_06_030000_add_coupon_type_to_coupons'
        File = '2026_08_06_030000_add_coupon_type_to_coupons.php'
        Sha256 = 'A75FA0F90587A7AD05B4D08F8A42E8CA07F592EE58466616C9A75C04B90CFA6E'
    },
    [pscustomobject]@{
        Name = '2026_08_06_040000_add_draft_to_orders_status'
        File = '2026_08_06_040000_add_draft_to_orders_status.php'
        Sha256 = 'F7360DF0C41CBCCFBECF2FF65E646D1625EBE8A5B47C2B228314B684D349403F'
    },
    [pscustomobject]@{
        Name = '2026_08_07_000001_add_rejection_reason_to_used_book_listings_table'
        File = '2026_08_07_000001_add_rejection_reason_to_used_book_listings_table.php'
        Sha256 = 'DCA6C2E977ED36D888A4718619ABE28B7D5795A36FBC0021E52E7C4B0CADB8B9'
    },
    [pscustomobject]@{
        Name = '2026_08_07_000002_backfill_used_book_warehouse_stocks_table'
        File = '2026_08_07_000002_backfill_used_book_warehouse_stocks_table.php'
        Sha256 = '542CD84A122BBCF6F856132E6F6F63B905A2F359A22C4553A081A75F7E0AB5C4'
    }
)

function Resolve-KomiBookPhpExecutable {
    param([string] $RequestedExecutable)

    if ($RequestedExecutable) {
        if (Test-Path -LiteralPath $RequestedExecutable -PathType Leaf) {
            return (Resolve-Path -LiteralPath $RequestedExecutable).Path
        }

        $command = Get-Command -Name $RequestedExecutable -CommandType Application -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($null -eq $command) {
            throw "PHP executable was not found: $RequestedExecutable. Supply -PhpExecutable with a FrankenPHP or PHP executable."
        }

        return $command.Source
    }

    $frankenPhpDefault = 'C:\runtimes\frankenphp\frankenphp.exe'
    if (Test-Path -LiteralPath $frankenPhpDefault -PathType Leaf) {
        return (Resolve-Path -LiteralPath $frankenPhpDefault).Path
    }

    foreach ($commandName in @('frankenphp', 'php')) {
        $command = Get-Command -Name $commandName -CommandType Application -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($null -ne $command) {
            return $command.Source
        }
    }

    throw 'No FrankenPHP or PHP executable was found. Supply -PhpExecutable explicitly.'
}

$candidate = (Resolve-Path -LiteralPath $CandidateBackend -ErrorAction Stop).Path
$artisan = Join-Path $candidate 'artisan'
$migrationDirectory = Join-Path $candidate 'database\migrations'

if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) {
    throw "Candidate backend does not contain artisan: $candidate"
}

if (-not (Test-Path -LiteralPath $migrationDirectory -PathType Container)) {
    throw "Candidate backend does not contain database\\migrations: $candidate"
}

foreach ($migration in $requiredMigrations) {
    $candidateMigration = Join-Path $migrationDirectory $migration.File
    if (-not (Test-Path -LiteralPath $candidateMigration -PathType Leaf)) {
        throw "KOMIBOOK_MIGRATION_PROVENANCE=FAIL migration=$($migration.Name) reason=missing-file"
    }

    $actualHash = (Get-FileHash -LiteralPath $candidateMigration -Algorithm SHA256).Hash.ToUpperInvariant()
    if ($actualHash -ne $migration.Sha256) {
        throw "KOMIBOOK_MIGRATION_PROVENANCE=FAIL migration=$($migration.Name) reason=sha256-mismatch expected=$($migration.Sha256) actual=$actualHash"
    }

    Write-Output "KOMIBOOK_MIGRATION_FILE=PASS migration=$($migration.Name) sha256=$actualHash"
}

$php = Resolve-KomiBookPhpExecutable -RequestedExecutable $PhpExecutable
$phpLeaf = [IO.Path]::GetFileNameWithoutExtension($php)
$artisanArguments = if ($phpLeaf -match '^frankenphp$') {
    @('php-cli', 'artisan', 'migrate:status', '--no-ansi')
} else {
    @('artisan', 'migrate:status', '--no-ansi')
}

Push-Location $candidate
try {
    $statusOutput = @(& $php @artisanArguments 2>&1)
    $statusExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}

if ($statusExitCode -ne 0) {
    throw "KOMIBOOK_MIGRATION_PROVENANCE=FAIL reason=migrate-status-command-failed exit-code=$statusExitCode candidate=$candidate runtime=$php"
}

$statusText = $statusOutput -join "`n"
foreach ($migration in $requiredMigrations) {
    $migrationPattern = '(?im)^\s*' + [regex]::Escape($migration.Name) + '\s+.*\bRan\s*$'
    if ($statusText -notmatch $migrationPattern) {
        throw "KOMIBOOK_MIGRATION_PROVENANCE=FAIL migration=$($migration.Name) reason=not-ran-or-not-reported"
    }

    Write-Output "KOMIBOOK_MIGRATION_STATUS=PASS migration=$($migration.Name) status=Ran"
}

Write-Output "KOMIBOOK_MIGRATION_PROVENANCE=PASS candidate=$candidate runtime=$php"
