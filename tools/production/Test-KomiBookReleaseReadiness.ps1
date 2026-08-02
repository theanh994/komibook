param(
    [Parameter(Mandatory = $true)]
    [string] $CandidateBackend,

    [string] $SharedEnv = 'C:\komibook_shared\.env',

    [string] $SharedAssets = 'C:\komibook_shared\assets',

    [string] $FrankenPhp = 'C:\runtimes\frankenphp\frankenphp.exe'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$candidate = (Resolve-Path -LiteralPath $CandidateBackend).Path
$releaseRoot = (Resolve-Path -LiteralPath 'C:\komibook_releases').Path
$sharedEnvPath = (Resolve-Path -LiteralPath $SharedEnv).Path
$sharedAssetsPath = (Resolve-Path -LiteralPath $SharedAssets).Path
$frankenPhpPath = (Resolve-Path -LiteralPath $FrankenPhp).Path

if (-not $candidate.StartsWith($releaseRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Candidate backend must be located under $releaseRoot."
}

$candidateEnv = Join-Path $candidate '.env'
$configCache = Join-Path $candidate 'bootstrap\cache\config.php'

if (-not (Test-Path -LiteralPath $candidateEnv -PathType Leaf)) {
    throw 'Candidate is not linked to the shared .env.'
}

if ((Get-FileHash -Algorithm SHA256 -LiteralPath $candidateEnv).Hash -ne (Get-FileHash -Algorithm SHA256 -LiteralPath $sharedEnvPath).Hash) {
    throw 'Candidate .env does not match the shared .env.'
}

if (-not (Test-Path -LiteralPath $configCache -PathType Leaf)) {
    throw 'Candidate does not have a production config cache.'
}

$releaseDirectory = Split-Path $candidate -Parent
$releaseSha = Split-Path $releaseDirectory -Leaf
$frontendIndex = Join-Path $releaseDirectory 'frontend\dist-social\index.html'
$assetNamespace = 'r' + $releaseSha.Substring(0, 8)

if (-not (Test-Path -LiteralPath $frontendIndex -PathType Leaf)) {
    throw 'Candidate does not have a production frontend index.'
}

$frontendHtml = [IO.File]::ReadAllText($frontendIndex)
$assetPrefix = "/assets/$assetNamespace/"
$assetReferences = [regex]::Matches($frontendHtml, [regex]::Escape($assetPrefix) + '[^"''<> ]+') |
    ForEach-Object { $_.Value } |
    Select-Object -Unique

if ($assetReferences.Count -eq 0) {
    throw 'Candidate frontend does not use its release asset namespace.'
}

$missingAssets = @($assetReferences | Where-Object {
    $relativePath = $_.Substring('/assets/'.Length)
    -not (Test-Path -LiteralPath (Join-Path $sharedAssetsPath $relativePath) -PathType Leaf)
})

if ($missingAssets.Count -gt 0) {
    throw "Candidate frontend has missing shared assets: $($missingAssets -join ', ')"
}

Push-Location $candidate
try {
    $readinessOutput = & $frankenPhpPath php-cli artisan production:readiness --json --no-ansi 2>&1
    if ($LASTEXITCODE -ne 0) {
        $readinessOutput | Write-Output
        throw 'Production readiness gate failed. Do not cut over.'
    }

    $pendingOutput = & $frankenPhpPath php-cli artisan migrate:status --pending --no-ansi 2>&1
    $pendingText = $pendingOutput -join "`n"
    $hasPendingMigration = $pendingText -match '(?m)^\s*\d{4}_\d{2}_\d{2}_\d{6}_.+\bPending\s*$'
    if ($LASTEXITCODE -ne 0 -or $hasPendingMigration) {
        $pendingOutput | Write-Output
        throw 'Candidate has pending migrations. Do not cut over.'
    }

    $readinessOutput | Write-Output
    Write-Output "KOMIBOOK_FRONTEND_ASSETS=PASS"
    Write-Output 'KOMIBOOK_RELEASE_READINESS=PASS'
} finally {
    Pop-Location
}
