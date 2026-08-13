[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ReleaseBackend,

    [string]$FrankenPhp = 'C:\runtimes\frankenphp\frankenphp.exe',

    [string]$LogDirectory = 'C:\komibook_shared\logs\chat-auto-resume'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ReleaseRoot = 'C:\komibook_releases'
$RuntimeRoot = 'C:\runtimes\frankenphp'
$SharedLogRoot = 'C:\komibook_shared\logs'
$LogRetentionDays = 14
$LogSizeLimitBytes = 4MB

function Resolve-ExistingFile([string]$Path, [string]$Phase) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "$Phase validation failed."
    }

    return [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $Path -ErrorAction Stop).Path)
}

function Resolve-ExistingDirectory([string]$Path, [string]$Phase) {
    if (-not (Test-Path -LiteralPath $Path -PathType Container)) {
        throw "$Phase validation failed."
    }

    return [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $Path -ErrorAction Stop).Path).TrimEnd('\\')
}

function Test-ChildPath([string]$Path, [string]$Root) {
    $prefix = $Root.TrimEnd('\\') + '\'

    return $Path.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)
}

function Get-ReleaseContext {
    $resolvedBackend = Resolve-ExistingDirectory $ReleaseBackend 'release backend'
    $resolvedReleaseRoot = Resolve-ExistingDirectory $ReleaseRoot 'release root'
    $releaseDirectory = Split-Path -Parent $resolvedBackend
    $sha = Split-Path -Leaf $releaseDirectory

    if ((Split-Path -Leaf $resolvedBackend) -cne 'backend' -or -not (Test-ChildPath $releaseDirectory $resolvedReleaseRoot) -or $sha -notmatch '^[0-9a-f]{40}$') {
        throw 'release backend validation failed.'
    }

    if ((Split-Path -Parent $releaseDirectory) -cne $resolvedReleaseRoot) {
        throw 'release backend validation failed.'
    }

    $artisan = Resolve-ExistingFile (Join-Path $resolvedBackend 'artisan') 'artisan'
    $resolvedFrankenPhp = Resolve-ExistingFile $FrankenPhp 'FrankenPHP executable'
    $resolvedRuntimeRoot = Resolve-ExistingDirectory $RuntimeRoot 'FrankenPHP runtime root'
    $expectedFrankenPhp = Resolve-ExistingFile (Join-Path $resolvedRuntimeRoot 'frankenphp.exe') 'allowlisted FrankenPHP executable'
    if (-not [string]::Equals($resolvedFrankenPhp, $expectedFrankenPhp, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'FrankenPHP executable validation failed.'
    }

    return [pscustomobject]@{
        Backend = $resolvedBackend
        Artisan = $artisan
        FrankenPhp = $resolvedFrankenPhp
        Sha = $sha
    }
}

function Get-ValidatedLogDirectory {
    $fullPath = [System.IO.Path]::GetFullPath($LogDirectory).TrimEnd('\\')
    $resolvedRoot = Resolve-ExistingDirectory $SharedLogRoot 'shared log root'
    if (-not (Test-ChildPath $fullPath $resolvedRoot)) {
        throw 'log directory validation failed.'
    }

    if (-not (Test-Path -LiteralPath $fullPath)) {
        New-Item -ItemType Directory -Path $fullPath -Force | Out-Null
    }

    return Resolve-ExistingDirectory $fullPath 'log directory'
}

function Write-AuditLog([string]$Directory, [string]$Sha, [string]$Phase, [string]$Result, [Nullable[int]]$ExitCode, [string]$ExceptionClass) {
    try {
        $date = (Get-Date).ToUniversalTime().ToString('yyyy-MM-dd')
        $path = Join-Path $Directory ("chat-auto-resume-$date.log")
        if ((Test-Path -LiteralPath $path -PathType Leaf) -and (Get-Item -LiteralPath $path).Length -ge $LogSizeLimitBytes) {
            $rotated = "$path.1"
            if (Test-Path -LiteralPath $rotated -PathType Leaf) {
                Remove-Item -LiteralPath $rotated -Force
            }
            Move-Item -LiteralPath $path -Destination $rotated -Force
        }

        $record = [ordered]@{
            timestamp_utc = (Get-Date).ToUniversalTime().ToString('o')
            release_sha = $Sha
            phase = $Phase
            result = $Result
        }
        if ($null -ne $ExitCode) {
            # [ordered]@{} produces an OrderedDictionary. Dot-property writes
            # are not supported reliably under Windows PowerShell 5.1 and can
            # make logging silently fail inside this best-effort boundary.
            $record['exit_code'] = $ExitCode
        }
        if ($ExceptionClass) {
            $record['exception_class'] = $ExceptionClass
        }
        ($record | ConvertTo-Json -Compress) | Add-Content -LiteralPath $path -Encoding UTF8

        Get-ChildItem -LiteralPath $Directory -File -Filter 'chat-auto-resume-*.log*' |
            Where-Object { $_.LastWriteTimeUtc -lt (Get-Date).ToUniversalTime().AddDays(-$LogRetentionDays) } |
            Remove-Item -Force
    } catch {
        # Logging must never disclose an error message or replace the workload exit code.
    }
}

function Test-LoopbackMySql([string]$Backend) {
    $envPath = Join-Path $Backend '.env'
    if (-not (Test-Path -LiteralPath $envPath -PathType Leaf)) {
        throw 'database prerequisite validation failed.'
    }

    $values = @{}
    foreach ($line in [System.IO.File]::ReadAllLines($envPath)) {
        if ($line -match '^\s*(DB_HOST|DB_PORT)\s*=\s*(.*?)\s*$') {
            $values[$matches[1]] = $matches[2].Trim().Trim('"').Trim("'")
        }
    }
    $hostName = [string]$values['DB_HOST']
    $port = 0
    [void][int]::TryParse([string]$values['DB_PORT'], [ref]$port)
    if ($hostName -notin @('127.0.0.1', 'localhost', '::1') -or $port -lt 1 -or $port -gt 65535) {
        throw 'database prerequisite validation failed.'
    }

    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $connection = $client.BeginConnect($hostName, $port, $null, $null)
        if (-not $connection.AsyncWaitHandle.WaitOne(3000) -or -not $client.Connected) {
            throw 'database prerequisite unavailable.'
        }
        $client.EndConnect($connection)
    } finally {
        $client.Close()
    }
}

$phase = 'validate'
$sha = 'unknown'
$auditDirectory = $null
try {
    $context = Get-ReleaseContext
    $sha = $context.Sha
    $auditDirectory = Get-ValidatedLogDirectory
    $phase = 'database_prerequisite'
    Test-LoopbackMySql $context.Backend
    $phase = 'artisan'

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $context.FrankenPhp
    $startInfo.Arguments = 'php-cli artisan chat:auto-resume-idle --limit=100 --no-ansi'
    $startInfo.WorkingDirectory = $context.Backend
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw 'artisan process start failed.'
    }

    # Event callbacks run on a thread without a PowerShell runspace under
    # Windows PowerShell 5.1. Drain both redirected streams asynchronously
    # instead; their contents are intentionally never emitted or logged.
    $stdoutTask = $process.StandardOutput.ReadToEndAsync()
    $stderrTask = $process.StandardError.ReadToEndAsync()
    $process.WaitForExit()
    [void]$stdoutTask.GetAwaiter().GetResult()
    [void]$stderrTask.GetAwaiter().GetResult()
    $exitCode = $process.ExitCode
    Write-AuditLog $auditDirectory $sha $phase $(if ($exitCode -eq 0) { 'success' } else { 'failure' }) $exitCode ''
    exit $exitCode
} catch {
    if ($null -ne $auditDirectory) {
        Write-AuditLog $auditDirectory $sha $phase 'exception' $null $_.Exception.GetType().FullName
    }
    exit 1
}
