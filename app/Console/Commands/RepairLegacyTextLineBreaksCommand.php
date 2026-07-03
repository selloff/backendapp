<?php

namespace App\Console\Commands;

use App\Support\LegacyTextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairLegacyTextLineBreaksCommand extends Command
{
    protected $signature = 'selloff:repair-legacy-text-line-breaks';

    protected $description = 'Fix product descriptions and blog content where legacy HTML or \\r\\n was imported incorrectly';

    public function handle(): int
    {
        $updated = 0;

        if (Schema::hasTable('product_translations') && Schema::hasColumn('product_translations', 'description')) {
            $updated += $this->repairTable('product_translations', 'id', ['description', 'short_description']);
        }

        if (Schema::hasTable('blog_posts')) {
            $updated += $this->repairTable('blog_posts', 'id', ['content', 'summary']);
        }

        if (Schema::hasTable('pages') && Schema::hasColumn('pages', 'content')) {
            $columns = ['content'];
            if (Schema::hasColumn('pages', 'description')) {
                $columns[] = 'description';
            }
            $updated += $this->repairTable('pages', 'id', $columns);
        }

        $this->info("Repaired {$updated} text field(s).");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $columns
     */
    private function repairTable(string $table, string $keyColumn, array $columns): int
    {
        $updated = 0;

        DB::table($table)
            ->orderBy($keyColumn)
            ->chunkById(200, function ($rows) use ($table, $keyColumn, $columns, &$updated): void {
                foreach ($rows as $row) {
                    $changes = [];

                    foreach ($columns as $column) {
                        if (! property_exists($row, $column)) {
                            continue;
                        }

                        $value = $row->{$column};
                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        $restored = LegacyTextNormalizer::normalizeImportedText($value);
                        if ($restored !== $value) {
                            $changes[$column] = $restored;
                        }
                    }

                    if ($changes === []) {
                        continue;
                    }

                    DB::table($table)->where($keyColumn, $row->{$keyColumn})->update($changes);
                    $updated += count($changes);
                }
            }, $keyColumn);

        return $updated;
    }
}
