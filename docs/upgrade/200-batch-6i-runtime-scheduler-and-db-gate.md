# Batch 6I: chat auto-resume runtime scheduler and DB gate

Batch 6I adds a repeatable, fail-closed Scheduled Task workflow for the active immutable KomiBook release. It is source-only: no task registration, service change, DBngin change, credential access, or production action is performed by this change.

## Scope and safety contract

`Invoke-KomiBookChatAutoResume.ps1` accepts only an exact backend path in `C:\komibook_releases\<40-lowercase-hex>\backend`, the release-local `artisan`, and `C:\runtimes\frankenphp\frankenphp.exe`. It probes only `DB_HOST` and `DB_PORT` from that release's `.env`, accepts the current loopback MySQL prerequisite (`127.0.0.1`, `localhost`, or `::1`), and verifies TCP reachability before starting Artisan. It never emits those values, credentials, command output, stderr, exception messages, or stack traces.

The launcher runs exactly:

```text
frankenphp.exe php-cli artisan chat:auto-resume-idle --limit=100 --no-ansi
```

from the exact release backend directory. Its one-line JSON audit records are bounded by daily file and 4 MB rotation plus 14-day retention, containing only UTC timestamp, release SHA, phase, result, exit code, and exception class.

The scheduled action is always `C:\Windows\System32\conhost.exe --headless C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ...`; this is mandatory no-popup behavior. `Bypass` is process-local and does not change machine or user execution policy. It never invokes Windows Terminal and never uses a bare PowerShell or FrankenPHP task action.

`C:\komibook_releases`, `C:\komibook_shared`, and `C:\runtimes\frankenphp` are trusted operational roots. Before using this workflow, ensure their ACLs prevent untrusted writes and that release, backend, launcher, runtime, and shared-log paths contain no reparse points/junctions that could redirect execution or logs. This is an operational prerequisite for this source-only workflow.

## Install or update after a successful cutover

Run this only after the new immutable release is activated and its release root includes `tools\production\Invoke-KomiBookChatAutoResume.ps1` alongside `backend`.

```powershell
$sha = '<active-40-lowercase-hex>'
$backend = "C:\komibook_releases\$sha\backend"
& "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "C:\komibook_releases\$sha\tools\production\Set-KomiBookChatAutoResumeTask.ps1" -ReleaseBackend $backend
```

The task is registered for the current interactive Windows user. Therefore it runs only while that user is logged in. It does not promise logged-out execution, boot continuity, or reboot continuity.

Without `-RunNow`, updater output confirms configuration only; it does not accept a prior `LastTaskResult` as runtime evidence. Runtime acceptance requires a separate `-RunNow` invocation. The updater validates all paths before mutation, saves an existing task XML in `C:\komibook_shared\scheduler-backups`, converges idempotently for the same SHA, and verifies action, argument string, working directory, enabled state, one-minute repetition, `IgnoreNew`, five-minute execution limit, and `StartWhenAvailable`. On failure after task mutation, it first stops a running task and waits up to 30 seconds before restore or disable. If it cannot confirm the stop, it disables and verifies the task instead of restoring/re-enabling it. A registration or verification failure otherwise restores the prior XML; if there is no restorable task it disables the task rather than leaving an unverified enabled action.

## Verification and acceptance

Use the active release SHA, never a checkout SHA:

```powershell
$sha = '<active-40-lowercase-hex>'
$backend = "C:\komibook_releases\$sha\backend"
& "C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "C:\komibook_releases\$sha\tools\production\Set-KomiBookChatAutoResumeTask.ps1" -ReleaseBackend $backend -RunNow -VerificationWaitSeconds 30
Get-ScheduledTask -TaskName KomiBook-Chat-AutoResume | Select-Object TaskName, State
Get-ScheduledTaskInfo -TaskName KomiBook-Chat-AutoResume | Select-Object LastRunTime, LastTaskResult
Get-Content "C:\komibook_shared\logs\chat-auto-resume\chat-auto-resume-$((Get-Date).ToUniversalTime().ToString('yyyy-MM-dd')).log" -Tail 20
& "C:\runtimes\frankenphp\frankenphp.exe" php-cli artisan production:readiness --json
```

Perform two runs: one explicit `-RunNow` immediately after registration, and one scheduled one-minute run. For both, confirm `LastTaskResult` is `0`, the latest audit record contains the same active SHA, and no window appears. Then perform the separate active-release readiness JSON, service checks, and public smoke checks required by the cutover runbook.

To roll back the task action, register the saved XML for the selected backup only after reviewing that backup. If no safe backup exists, disable rather than delete the task:

```powershell
Disable-ScheduledTask -TaskName KomiBook-Chat-AutoResume
```

## DBngin blocking prerequisite (option C)

The existing DBngin/MySQL datadir is an external blocking prerequisite. Batch 6I does not install a Windows service, modify DBngin configuration, change its data directory, or claim reboot behavior. Batch 6 operational closure remains blocked until an explicitly approved reboot test proves that MySQL is reachable on port 3306 before manual intervention, active-release `production:readiness --json` is ready, the scheduler's `LastTaskResult` is `0`, and service plus public smoke checks pass.
