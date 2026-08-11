<?php

namespace App\Console\Commands;

use App\Services\LegacyDemoAuthorityRepairService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RepairLegacyDemoAuthority extends Command
{
    protected $signature = 'organization-authority:repair-legacy-demo
        {--apply : Write the reviewed repair after every safeguard has passed}
        {--admin-id= : Current administrator user id required with --apply}
        {--reason= : Audit reason required with --apply}
        {--manifest-digest= : Exact digest emitted by the current dry-run}
        {--json : Emit the deterministic manifest as JSON}';

    protected $description = 'Inspect or safely recover repository-proven legacy demo authority only.';

    public function handle(LegacyDemoAuthorityRepairService $repair): int
    {
        try {
            if (! $this->option('apply')) {
                return $this->render($repair->inspect());
            }
            $adminId = filter_var($this->option('admin-id'), FILTER_VALIDATE_INT);
            $reason = trim((string) $this->option('reason'));
            $digest = trim((string) $this->option('manifest-digest'));
            if ($adminId === false || $adminId === null || $reason === '' || $digest === '') {
                $this->error('Apply requires --admin-id, a nonblank --reason, and --manifest-digest from the current dry-run.');

                return self::FAILURE;
            }

            return $this->render($repair->apply((int) $adminId, $reason, $digest));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?? 'Repair validation failed.');
        } catch (Throwable) {
            $this->error('Legacy demo authority repair failed. No changes were committed.');
        }

        return self::FAILURE;
    }

    /** @param array<string, mixed> $manifest */
    private function render(array $manifest): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->info(($manifest['applied'] ?? false) ? 'Applied repair manifest' : 'Dry-run manifest (no writes)');
            $this->line('Digest: '.$manifest['digest']);
            $this->line(sprintf(
                'Candidates: %d organizations, %d relationships, %d agreements, %d book parties.',
                count($manifest['candidates']['organization_ids']),
                count($manifest['candidates']['relationship_ids']),
                count($manifest['candidates']['agreement_ids']),
                count($manifest['candidates']['book_commercial_party_ids']),
            ));
            $this->line('Conflicts: '.count($manifest['conflicts']).' (blocking: '.$manifest['blocking_conflict_count'].').');
        }

        return self::SUCCESS;
    }
}
