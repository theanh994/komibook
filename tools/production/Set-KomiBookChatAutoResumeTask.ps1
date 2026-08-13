[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ReleaseBackend,

    [string]$FrankenPhp = 'C:\runtimes\frankenphp\frankenphp.exe',

    [string]$LogDirectory = 'C:\komibook_shared\logs\chat-auto-resume',

    [string]$TaskName = 'KomiBook-Chat-AutoResume',

    [switch]$RunNow,

    [ValidateRange(5, 300)]
    [int]$VerificationWaitSeconds = 30
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ReleaseRoot = 'C:\komibook_releases'
$RuntimeRoot = 'C:\runtimes\frankenphp'
$SharedLogRoot = 'C:\komibook_shared\logs'
$SharedBackupRoot = 'C:\komibook_shared\scheduler-backups'
$Conhost = 'C:\Windows\System32\conhost.exe'
$PowerShell = 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe'

function Resolve-ExistingFile([string]$Path, [string]$Phase) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "$Phase validation failed." }
    [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $Path -ErrorAction Stop).Path)
}

function Resolve-ExistingDirectory([string]$Path, [string]$Phase) {
    if (-not (Test-Path -LiteralPath $Path -PathType Container)) { throw "$Phase validation failed." }
    [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $Path -ErrorAction Stop).Path).TrimEnd('\\')
}

function Test-ChildPath([string]$Path, [string]$Root) {
    $Path.StartsWith($Root.TrimEnd('\\') + '\', [System.StringComparison]::OrdinalIgnoreCase)
}

function Quote-TaskArgument([string]$Value) {
    '"' + $Value.Replace('"', '\"') + '"'
}

function Test-CurrentWindowsPrincipal([string]$TaskUserId) {
    if ([string]::IsNullOrWhiteSpace($TaskUserId)) { return $false }

    $currentIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent()
    if ($null -eq $currentIdentity.User) { throw 'current Windows identity SID is unavailable.' }

    # Task Scheduler can normalize a local interactive identity from
    # MACHINE\\user to user. Resolve every representation to a SID and only
    # accept the task when it is the SID of the interactive caller.
    $candidates = New-Object System.Collections.Generic.List[string]
    $candidates.Add($TaskUserId)
    if ($TaskUserId -notmatch '[\\@]') {
        $separator = $currentIdentity.Name.LastIndexOf('\\')
        if ($separator -gt 0) {
            $candidates.Add($currentIdentity.Name.Substring(0, $separator + 1) + $TaskUserId)
        }
    }

    foreach ($candidate in ($candidates | Select-Object -Unique)) {
        try {
            $account = New-Object System.Security.Principal.NTAccount($candidate)
            $sid = $account.Translate([System.Security.Principal.SecurityIdentifier]).Value
            if ([string]::Equals($sid, $currentIdentity.User.Value, [System.StringComparison]::OrdinalIgnoreCase)) {
                return $true
            }
        } catch [System.Security.Principal.IdentityNotMappedException] {
            # Continue with a machine-qualified candidate when Scheduler returned a bare local account name.
        }
    }

    return $false
}

function Get-Context {
    if ($TaskName -notmatch '^[A-Za-z0-9_.-]{1,100}$') { throw 'task name validation failed.' }
    $backend = Resolve-ExistingDirectory $ReleaseBackend 'release backend'
    $releaseRoot = Resolve-ExistingDirectory $ReleaseRoot 'release root'
    $releaseDirectory = Split-Path -Parent $backend
    $sha = Split-Path -Leaf $releaseDirectory
    if ((Split-Path -Leaf $backend) -cne 'backend' -or (Split-Path -Parent $releaseDirectory) -cne $releaseRoot -or $sha -notmatch '^[0-9a-f]{40}$') { throw 'release backend validation failed.' }
    [void](Resolve-ExistingFile (Join-Path $backend 'artisan') 'artisan')
    $launcher = Resolve-ExistingFile (Join-Path $releaseDirectory 'tools\production\Invoke-KomiBookChatAutoResume.ps1') 'release launcher'
    $frankenPhp = Resolve-ExistingFile $FrankenPhp 'FrankenPHP executable'
    $runtimeRoot = Resolve-ExistingDirectory $RuntimeRoot 'FrankenPHP runtime root'
    $expectedFrankenPhp = Resolve-ExistingFile (Join-Path $runtimeRoot 'frankenphp.exe') 'allowlisted FrankenPHP executable'
    if (-not [string]::Equals($frankenPhp, $expectedFrankenPhp, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'FrankenPHP executable validation failed.' }
    $logs = [System.IO.Path]::GetFullPath($LogDirectory).TrimEnd('\\')
    if (-not (Test-ChildPath $logs (Resolve-ExistingDirectory $SharedLogRoot 'shared log root'))) { throw 'log directory validation failed.' }
    if (-not (Test-Path -LiteralPath $logs)) { New-Item -ItemType Directory -Path $logs -Force | Out-Null }
    $logs = Resolve-ExistingDirectory $logs 'log directory'
    [pscustomobject]@{ Backend = $backend; ReleaseDirectory = $releaseDirectory; Sha = $sha; Launcher = $launcher; FrankenPhp = $frankenPhp; LogDirectory = $logs }
}

function Backup-ExistingTask {
    $existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($null -eq $existing) { return $null }
    $backupRoot = [System.IO.Path]::GetFullPath($SharedBackupRoot).TrimEnd('\\')
    if (-not (Test-ChildPath $backupRoot (Resolve-ExistingDirectory 'C:\komibook_shared' 'shared root'))) { throw 'backup directory validation failed.' }
    if (-not (Test-Path -LiteralPath $backupRoot)) { New-Item -ItemType Directory -Path $backupRoot -Force | Out-Null }
    $backup = Join-Path $backupRoot ("$TaskName-" + (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ') + '.xml')
    [System.IO.File]::WriteAllText($backup, ([string](Export-ScheduledTask -TaskName $TaskName -ErrorAction Stop)), [System.Text.Encoding]::UTF8)
    Get-ChildItem -LiteralPath $backupRoot -File -Filter "$TaskName-*.xml" | Sort-Object LastWriteTimeUtc -Descending | Select-Object -Skip 10 | Remove-Item -Force
    $backup
}

function Test-TaskConfiguration([pscustomobject]$Context) {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop
    $action = @($task.Actions)
    $trigger = @($task.Triggers)
    $expectedArguments = '--headless ' + (Quote-TaskArgument $PowerShell) + ' -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' + (Quote-TaskArgument $Context.Launcher) + ' -ReleaseBackend ' + (Quote-TaskArgument $Context.Backend) + ' -FrankenPhp ' + (Quote-TaskArgument $Context.FrankenPhp) + ' -LogDirectory ' + (Quote-TaskArgument $Context.LogDirectory)
    if ($action.Count -ne 1 -or $action[0].Execute -cne $Conhost -or $action[0].Arguments -cne $expectedArguments -or $action[0].WorkingDirectory -cne $Context.Backend) { throw 'task action verification failed.' }
    if (-not (Test-CurrentWindowsPrincipal ([string]$task.Principal.UserId)) -or $task.Principal.LogonType -notin @('Interactive', 'InteractiveToken') -or $task.Principal.RunLevel -ne 'Limited') { throw 'task principal verification failed.' }
    if ($trigger.Count -ne 1 -or $trigger[0].CimClass.CimClassName -ne 'MSFT_TaskTimeTrigger' -or -not $trigger[0].Enabled -or [string]::IsNullOrWhiteSpace([string]$trigger[0].StartBoundary) -or $trigger[0].Repetition.Interval -ne 'PT1M' -or $trigger[0].Repetition.Duration -ne 'P3650D') { throw 'task trigger verification failed.' }
    if (-not $task.Settings.Enabled -or -not $task.Settings.AllowDemandStart -or $task.Settings.Hidden -or $task.Settings.RunOnlyIfIdle -or $task.Settings.DisallowStartIfOnBatteries -or $task.Settings.StopIfGoingOnBatteries -or $task.Settings.MultipleInstances -ne 'IgnoreNew' -or -not $task.Settings.StartWhenAvailable -or $task.Settings.ExecutionTimeLimit -ne 'PT5M') { throw 'task settings verification failed.' }
}

function Get-TaskActionSignature([string]$Xml) {
    [xml]$document = $Xml
    $namespace = New-Object System.Xml.XmlNamespaceManager($document.NameTable)
    $namespace.AddNamespace('task', 'http://schemas.microsoft.com/windows/2004/02/mit/task')
    $exec = $document.SelectSingleNode('//task:Actions/task:Exec', $namespace)
    if ($null -eq $exec) { throw 'task XML verification failed.' }
    [pscustomobject]@{
        Command = [string]$exec.Command
        Arguments = [string]$exec.Arguments
        WorkingDirectory = [string]$exec.WorkingDirectory
    }
}

function Get-TaskConfigurationSignature([string]$Xml) {
    [xml]$document = $Xml
    $namespace = New-Object System.Xml.XmlNamespaceManager($document.NameTable)
    $namespace.AddNamespace('task', 'http://schemas.microsoft.com/windows/2004/02/mit/task')
    $nodes = @('Actions', 'Principals', 'Triggers', 'Settings') | ForEach-Object {
        $node = $document.SelectSingleNode("/task:Task/task:$_", $namespace)
        if ($null -eq $node) { throw 'task XML verification failed.' }

        $node.OuterXml
    }

    return ($nodes -join "`n")
}

function Disable-TaskAndVerify {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($null -eq $task) { return }

    Disable-ScheduledTask -TaskName $TaskName -ErrorAction Stop | Out-Null
    $disabled = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop
    if ($disabled.Settings.Enabled) { throw 'task disable verification failed.' }
}

function Stop-RunningTaskForRollback {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($null -eq $task -or $task.State -ne 'Running') { return }

    Stop-ScheduledTask -TaskName $TaskName -ErrorAction Stop
    $deadline = (Get-Date).AddSeconds(30)
    do {
        Start-Sleep -Milliseconds 500
        $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop
    } while ($task.State -eq 'Running' -and (Get-Date) -lt $deadline)

    if ($task.State -eq 'Running') { throw 'task stop verification failed.' }
}

function Restore-Or-Disable([string]$Backup) {
    $restoreFailed = $false
    try {
        Stop-RunningTaskForRollback
    } catch {
        try {
            Disable-TaskAndVerify
        } catch {
            throw 'task rollback stop and disable verification failed.'
        }

        return 'stop-failed-task-disabled'
    }

    try {
        if ($Backup) {
            $backupXml = [System.IO.File]::ReadAllText($Backup)
            $expected = Get-TaskActionSignature $backupXml
            $expectedConfiguration = Get-TaskConfigurationSignature $backupXml
            Register-ScheduledTask -TaskName $TaskName -Xml $backupXml -Force | Out-Null
            $restoredXml = [string](Export-ScheduledTask -TaskName $TaskName -ErrorAction Stop)
            $actual = Get-TaskActionSignature $restoredXml
            $actualConfiguration = Get-TaskConfigurationSignature $restoredXml
            if ($actual.Command -cne $expected.Command -or $actual.Arguments -cne $expected.Arguments -or $actual.WorkingDirectory -cne $expected.WorkingDirectory -or $actualConfiguration -cne $expectedConfiguration) { throw 'task restore verification failed.' }
            return 'restored'
        }
    } catch {
        $restoreFailed = $true
    }

    try {
        Disable-TaskAndVerify
    } catch {
        throw 'task rollback disable verification failed.'
    }

    if ($restoreFailed) { return 'restore-failed-task-disabled' }

    return 'disabled'
}

$backup = $null
$mutationAttempted = $false
try {
    $context = Get-Context
    $backup = Backup-ExistingTask
    $arguments = '--headless ' + (Quote-TaskArgument $PowerShell) + ' -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' + (Quote-TaskArgument $context.Launcher) + ' -ReleaseBackend ' + (Quote-TaskArgument $context.Backend) + ' -FrankenPhp ' + (Quote-TaskArgument $context.FrankenPhp) + ' -LogDirectory ' + (Quote-TaskArgument $context.LogDirectory)
    $action = New-ScheduledTaskAction -Execute $Conhost -Argument $arguments -WorkingDirectory $context.Backend
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -StartWhenAvailable
    $principal = New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
    $mutationAttempted = $true
    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force | Out-Null
    Test-TaskConfiguration $context

    if ($RunNow) {
        $baseline = Get-ScheduledTaskInfo -TaskName $TaskName -ErrorAction Stop
        $previousLastRun = $baseline.LastRunTime
        Start-ScheduledTask -TaskName $TaskName -ErrorAction Stop
        $deadline = (Get-Date).AddSeconds($VerificationWaitSeconds)
        do {
            Start-Sleep -Milliseconds 500
            $info = Get-ScheduledTaskInfo -TaskName $TaskName -ErrorAction Stop
            $state = (Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop).State
            $hasNewRun = $info.LastRunTime -gt $previousLastRun
        } while ((-not $hasNewRun -or $state -eq 'Running') -and (Get-Date) -lt $deadline)
        if (-not $hasNewRun -or $state -eq 'Running' -or $info.LastTaskResult -ne 0) { throw 'scheduled run verification failed.' }

        Write-Output "Verified $TaskName runtime execution for release $($context.Sha). Interactive-user task: it runs only while this user is logged in; it does not provide logged-out or reboot continuity."
        exit 0
    }

    Write-Output "Verified $TaskName configuration for release $($context.Sha). Runtime execution and LastTaskResult acceptance require a separate -RunNow verification. Interactive-user task: it runs only while this user is logged in; it does not provide logged-out or reboot continuity."
} catch {
    $rollbackOutcome = 'not-needed'
    if ($mutationAttempted) {
        try {
            $rollbackOutcome = Restore-Or-Disable $backup
        } catch {
            $rollbackOutcome = 'restore-or-disable-failed'
        }
    }
    Write-Error "KomiBook chat auto-resume task verification failed; rollback outcome: $rollbackOutcome."
    exit 1
}
