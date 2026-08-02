param(
    [Parameter(Mandatory = $true)]
    [string] $CandidateFrontend,

    [string] $SharedAssets = 'C:\komibook_shared\assets'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$frontend = (Resolve-Path -LiteralPath $CandidateFrontend).Path
$releaseRoot = (Resolve-Path -LiteralPath 'C:\komibook_releases').Path
$sharedAssetsPath = (Resolve-Path -LiteralPath $SharedAssets).Path
$releaseDirectory = Split-Path $frontend -Parent
$releaseSha = Split-Path $releaseDirectory -Leaf

if (-not $frontend.StartsWith($releaseRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Candidate frontend must be located under the release root.'
}

if ($releaseSha -notmatch '^[0-9a-f]{40}$') {
    throw 'Release directory must use a full Git SHA.'
}

$namespace = 'r' + $releaseSha.Substring(0, 8)
$dist = Join-Path $frontend 'dist-social'
$sourceAssets = Join-Path $dist 'assets'
$index = Join-Path $dist 'index.html'
$targetAssets = Join-Path $sharedAssetsPath $namespace

if (-not (Test-Path -LiteralPath $sourceAssets -PathType Container) -or -not (Test-Path -LiteralPath $index -PathType Leaf)) {
    throw 'Production frontend bundle is incomplete.'
}

if (-not (Test-Path -LiteralPath $targetAssets)) {
    New-Item -ItemType Directory -Path $targetAssets | Out-Null
}

Copy-Item -Path (Join-Path $sourceAssets '*') -Destination $targetAssets -Recurse -Force

$html = [IO.File]::ReadAllText($index)
$assetPrefix = "/assets/$namespace/"
if (-not $html.Contains($assetPrefix)) {
    if ($html -match '/assets/r[0-9a-f]{7,40}/') {
        throw "Production index already uses a different release asset namespace."
    }

    if (-not $html.Contains('/assets/')) {
        throw 'Production index does not contain asset references.'
    }

    $backup = Join-Path $dist "index.pre-$namespace.bak"
    if (-not (Test-Path -LiteralPath $backup)) {
        Copy-Item -LiteralPath $index -Destination $backup
    }

    $html = $html.Replace('/assets/', $assetPrefix)
    [IO.File]::WriteAllText($index, $html, [Text.UTF8Encoding]::new($false))
}

$references = [regex]::Matches($html, [regex]::Escape($assetPrefix) + '[^"''<> ]+') |
    ForEach-Object { $_.Value } |
    Select-Object -Unique

if ($references.Count -eq 0) {
    throw 'No versioned frontend asset references were found.'
}

$missing = @($references | Where-Object {
    $relativePath = $_.Substring($assetPrefix.Length)
    -not (Test-Path -LiteralPath (Join-Path $targetAssets $relativePath) -PathType Leaf)
})

if ($missing.Count -gt 0) {
    throw "Versioned frontend assets are missing: $($missing -join ', ')"
}

Write-Output "KOMIBOOK_FRONTEND_ASSETS=PASS"
Write-Output "ASSET_NAMESPACE=$namespace"
Write-Output "ASSET_REFERENCES=$($references.Count)"
