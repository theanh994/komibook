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

if (Test-Path -LiteralPath $targetAssets) {
    throw "Release asset namespace already exists and must remain immutable: $targetAssets"
}

$assetPrefix = "/assets/$namespace/"
$relativeAssetPrefix = "assets/$namespace/"
$versionedAssetPattern = '(?<![A-Za-z0-9_.-])/?assets/r[0-9a-f]{8}/'
$unversionedRelativeAssetPattern = '(?<![/A-Za-z0-9_.-])assets/(?!r[0-9a-f]{8}/)'
$textArtifacts = @(
    Get-Item -LiteralPath $index
    Get-ChildItem -LiteralPath $sourceAssets -Recurse -File |
        Where-Object { $_.Extension -in @('.js', '.css', '.html', '.json') }
)

foreach ($artifact in $textArtifacts) {
    $content = [IO.File]::ReadAllText($artifact.FullName)
    $foreignNamespaces = @(
        [regex]::Matches($content, $versionedAssetPattern) |
            ForEach-Object { $_.Value.TrimStart('/') } |
            Where-Object { $_ -ne $relativeAssetPrefix } |
            Select-Object -Unique
    )

    if ($foreignNamespaces.Count -gt 0) {
        throw "Frontend artifact already references a different release namespace: $($foreignNamespaces -join ', ')"
    }

    $content = [regex]::Replace(
        $content,
        '/assets/(?!r[0-9a-f]{8}/)',
        [System.Text.RegularExpressions.MatchEvaluator] { param($match) $assetPrefix }
    )
    $content = [regex]::Replace(
        $content,
        $unversionedRelativeAssetPattern,
        [System.Text.RegularExpressions.MatchEvaluator] { param($match) $relativeAssetPrefix }
    )
    $content = $content.Replace('/favicon.ico', $assetPrefix + 'favicon.ico')

    if (
        [regex]::IsMatch($content, '/assets/(?!r[0-9a-f]{8}/)') -or
        [regex]::IsMatch($content, $unversionedRelativeAssetPattern)
    ) {
        throw "Frontend artifact still contains an unversioned asset reference: $($artifact.FullName)"
    }

    [IO.File]::WriteAllText($artifact.FullName, $content, [Text.UTF8Encoding]::new($false))
}

$html = [IO.File]::ReadAllText($index)
if (-not $html.Contains($assetPrefix)) {
    throw 'Production index does not contain versioned asset references after rewriting.'
}

$stagingAssets = Join-Path $sharedAssetsPath ".$namespace.staging-$PID-$([guid]::NewGuid().ToString('N'))"
$published = $false

New-Item -ItemType Directory -Path $stagingAssets | Out-Null

try {
    Copy-Item -Path (Join-Path $sourceAssets '*') -Destination $stagingAssets -Recurse

    $rootPublicFiles = @(
        Get-ChildItem -LiteralPath $dist -File |
            Where-Object {
                $_.Name -ne 'index.html' -and
                $_.Name -notlike 'index.pre-*.bak'
            }
    )

    foreach ($publicFile in $rootPublicFiles) {
        $destination = Join-Path $stagingAssets $publicFile.Name
        if (Test-Path -LiteralPath $destination) {
            throw "Duplicate root public asset in release namespace: $($publicFile.Name)"
        }

        Copy-Item -LiteralPath $publicFile.FullName -Destination $destination
    }

    $references = @(
        $textArtifacts |
            ForEach-Object {
                $artifactContent = [IO.File]::ReadAllText($_.FullName)
                [regex]::Matches(
                    $artifactContent,
                    '(?<![A-Za-z0-9_.-])/?' + [regex]::Escape($relativeAssetPrefix) + '[^"''<> )`,;]+'
                ) |
                    ForEach-Object {
                        if ($_.Value.StartsWith('/')) { $_.Value } else { '/' + $_.Value }
                    }
            } |
            Select-Object -Unique
    )

    if ($references.Count -eq 0) {
        throw 'No versioned frontend asset references were found.'
    }

    $missing = @($references | Where-Object {
        $relativePath = $_.Substring($assetPrefix.Length) -replace '[?#].*$', ''
        -not (Test-Path -LiteralPath (Join-Path $stagingAssets $relativePath) -PathType Leaf)
    })

    if ($missing.Count -gt 0) {
        throw "Versioned frontend assets are missing: $($missing -join ', ')"
    }

    Move-Item -LiteralPath $stagingAssets -Destination $targetAssets
    $published = $true
} finally {
    if (-not $published -and (Test-Path -LiteralPath $stagingAssets)) {
        Remove-Item -LiteralPath $stagingAssets -Recurse -Force
    }
}

Write-Output "KOMIBOOK_FRONTEND_ASSETS=PASS"
Write-Output "ASSET_NAMESPACE=$namespace"
Write-Output "ASSET_REFERENCES=$($references.Count)"
