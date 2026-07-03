<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RepairProductModerationFlagsCommand extends Command
{
    protected $signature = 'selloff:repair-product-moderation-flags
                            {--dry-run : Report changes without writing}';

    protected $description = 'Repair is_verified and legacy status/visibility encodings on imported products';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $statusPublishedUpdated = $this->normalizeStatus($dryRun, ['1'], 'published');
        $statusPendingUpdated = $this->normalizeStatus($dryRun, ['0'], 'pending', fn (Builder $query) => $query->where('is_draft', false));
        $visibilityVisibleUpdated = $this->normalizeVisibility($dryRun, ['1'], 'visible');
        $visibilityHiddenUpdated = $this->normalizeVisibility($dryRun, ['0'], 'hidden');

        $misdraftedQuery = Product::query()
            ->where('is_draft', false)
            ->where('is_deleted', false)
            ->where('status', 'draft')
            ->where(function (Builder $query): void {
                $query->where('visibility', 'visible')->orWhere('visibility', '1');
            })
            ->where('is_verified', true);

        $misdraftedCount = (clone $misdraftedQuery)->count();

        $approvedQuery = Product::query()
            ->where('status', 'published')
            ->where('is_draft', false)
            ->where('is_deleted', false)
            ->where(function (Builder $query): void {
                $query->whereNull('reject_reason')->orWhere('reject_reason', '');
            })
            ->where('is_verified', false);

        $approvedCount = (clone $approvedQuery)->count();

        $pendingQuery = Product::query()
            ->where('status', 'pending')
            ->where('is_verified', true);

        $pendingCount = (clone $pendingQuery)->count();

        $hiddenQuery = Product::query()
            ->where('status', 'hidden')
            ->where('is_verified', true);

        $hiddenCount = (clone $hiddenQuery)->count();

        $this->info("Published listings to mark verified: {$approvedCount}");
        $this->info("Pending listings to mark unverified: {$pendingCount}");
        $this->info("Hidden/rejected listings to mark unverified: {$hiddenCount}");
        $this->info("Status normalized to published: {$statusPublishedUpdated}");
        $this->info("Status normalized to pending: {$statusPendingUpdated}");
        $this->info("Visibility normalized to visible: {$visibilityVisibleUpdated}");
        $this->info("Visibility normalized to hidden: {$visibilityHiddenUpdated}");
        $this->info("Active listings with status=draft but is_draft=false: {$misdraftedCount}");

        if ($dryRun) {
            $this->warn('Dry run only — no rows updated.');

            return self::SUCCESS;
        }

        $misdraftedUpdated = $misdraftedQuery->update(['status' => 'published']);

        $approvedUpdated = $approvedQuery->update(['is_verified' => true]);
        $pendingUpdated = $pendingQuery->update(['is_verified' => false]);
        $hiddenUpdated = $hiddenQuery->update(['is_verified' => false]);

        $this->info("Updated {$approvedUpdated} published, {$pendingUpdated} pending, and {$hiddenUpdated} hidden products.");
        $this->info("Restored {$misdraftedUpdated} misclassified draft-status listings to published.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $from
     */
    private function normalizeStatus(bool $dryRun, array $from, string $to, ?callable $extra = null): int
    {
        $query = Product::query()->whereIn('status', $from);
        if ($extra) {
            $extra($query);
        }

        $count = (clone $query)->count();
        if (! $dryRun && $count > 0) {
            $query->update(['status' => $to]);
        }

        return $count;
    }

    /**
     * @param  list<string>  $from
     */
    private function normalizeVisibility(bool $dryRun, array $from, string $to): int
    {
        $query = Product::query()->whereIn('visibility', $from);
        $count = (clone $query)->count();
        if (! $dryRun && $count > 0) {
            $query->update(['visibility' => $to]);
        }

        return $count;
    }
}
