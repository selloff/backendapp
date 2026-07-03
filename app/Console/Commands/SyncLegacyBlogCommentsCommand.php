<?php

namespace App\Console\Commands;

use App\LegacyImport\Data\LegacyBlogComments;
use App\LegacyImport\Sync\LegacyBlogCommentsSync;
use Illuminate\Console\Command;

class SyncLegacyBlogCommentsCommand extends Command
{
    protected $signature = 'selloff:sync-legacy-blog-comments';

    protected $description = 'Upsert production legacy blog comments after blog posts are imported';

    public function handle(LegacyBlogCommentsSync $sync): int
    {
        $total = count(LegacyBlogComments::rows());
        $synced = $sync->sync();
        $skipped = $total - $synced;

        $this->info("Synced {$synced} of {$total} legacy blog comments.");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} comments because their legacy blog post is not in the database yet.");
            $this->line('Import blog posts first, then re-run this command.');
        }

        return self::SUCCESS;
    }
}
