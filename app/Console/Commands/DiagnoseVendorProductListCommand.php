<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\LegacyVendorProductListFilter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class DiagnoseVendorProductListCommand extends Command
{
    protected $signature = 'selloff:diagnose-vendor-product-list
                            {--vendor= : Vendor user id}
                            {--sku= : Product SKU}
                            {--title= : Partial product title}
                            {--id= : Product id}';

    protected $description = 'Explain why a vendor product is included or excluded from Items for sale';

    public function handle(): int
    {
        $query = Product::query()->with(['translations']);

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        if ($sku = $this->option('sku')) {
            $query->where('sku', $sku);
        }

        if ($title = $this->option('title')) {
            $term = '%'.$title.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->whereHas('translations', fn (Builder $translation) => $translation->where('title', 'like', $term))
                    ->orWhere('sku', 'like', $term);
            });
        }

        if ($vendorId = $this->option('vendor')) {
            $query->where('vendor_id', (int) $vendorId);
        }

        $products = $query->limit(10)->get();

        if ($products->isEmpty()) {
            $this->error('No matching products found.');

            return self::FAILURE;
        }

        foreach ($products as $product) {
            $title = $product->translations->first()?->title ?? '(no title)';
            $this->line('');
            $this->info("Product #{$product->id} — {$title}");
            $this->table(['Field', 'Value'], [
                ['vendor_id', (string) $product->vendor_id],
                ['sku', (string) $product->sku],
                ['status', (string) $product->status],
                ['visibility', (string) $product->visibility],
                ['is_draft', $product->is_draft ? 'true' : 'false'],
                ['is_deleted', $product->is_deleted ? 'true' : 'false'],
                ['is_verified', $product->is_verified ? 'true' : 'false'],
                ['is_affiliate', $product->is_affiliate ? 'true' : 'false'],
                ['is_active', $product->is_active ? 'true' : 'false'],
                ['is_sold', $product->is_sold ? 'true' : 'false'],
            ]);

            $checks = [
                'not deleted' => ! $product->is_deleted,
                'not draft' => ! $product->is_draft,
                'published status' => in_array((string) $product->status, ['published', '1'], true),
                'visible' => in_array((string) $product->visibility, ['visible', '1'], true),
            ];

            foreach ($checks as $label => $passes) {
                $this->line(sprintf('  [%s] %s', $passes ? 'ok' : 'FAIL', $label));
            }

            if ((string) $product->status === 'draft' && ! $product->is_draft) {
                $this->warn('  status=draft with is_draft=false — run: php artisan selloff:repair-product-moderation-flags');
            }

            $inList = Product::query()
                ->where('id', $product->id)
                ->where(function (Builder $scoped) use ($product): void {
                    $scoped->where('vendor_id', $product->vendor_id);
                    LegacyVendorProductListFilter::itemsForSale($scoped);
                })
                ->exists();

            $this->line($inList
                ? '  => Included in Items for sale list.'
                : '  => EXCLUDED from Items for sale list.');
        }

        return self::SUCCESS;
    }
}
