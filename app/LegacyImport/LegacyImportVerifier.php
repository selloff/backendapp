<?php

namespace App\LegacyImport;

use App\Services\Media\MediaUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LegacyImportVerifier
{
    public function __construct(
        private readonly MediaUploadService $mediaUpload,
    ) {}

    /**
     * @param  array<string, array{planned?: int, imported?: int, skipped?: int}>|null  $importStats
     */
    public function verify(
        ?MySqlDumpReader $reader = null,
        int $imageUrlSampleSize = 0,
        ?array $importStats = null,
    ): LegacyImportVerificationResult {
        $errors = [];
        $warnings = [];

        foreach (config('selloff.legacy_import.verify_tables', []) as $legacyTable => $newTable) {
            $expected = $this->expectedLegacyRowCount($reader, $legacyTable);
            $mapQuery = DB::table('legacy_import_maps')
                ->where('legacy_table', $legacyTable)
                ->where('new_table', $newTable);

            $mappedRows = (int) $mapQuery->count();
            $mappedDistinct = (int) (clone $mapQuery)->distinct()->count('new_id');

            $actual = $this->actualTableCount($newTable);

            if ($mappedRows === 0) {
                if ($expected !== null && $expected > 0) {
                    $warnings[] = "{$legacyTable}: {$expected} legacy rows not mapped (likely skipped during import)";
                }

                continue;
            }

            if ($mappedDistinct !== $actual) {
                $errors[] = "{$legacyTable}: {$mappedDistinct} distinct {$newTable} ids in legacy_import_maps but {$newTable} has {$actual}";
            } elseif ($mappedRows > $mappedDistinct) {
                $merged = $mappedRows - $mappedDistinct;
                $warnings[] = "{$legacyTable}: {$merged} legacy rows merged into existing {$newTable} rows during import (duplicate natural keys)";
            }

            if ($expected !== null && $mappedRows !== $expected) {
                $skipped = $importStats[$legacyTable]['skipped'] ?? ($expected - $mappedRows);
                if ($importStats !== null
                    && ($importStats[$legacyTable]['imported'] ?? $mappedRows) === $mappedRows
                    && ($importStats[$legacyTable]['imported'] ?? 0) + ($importStats[$legacyTable]['skipped'] ?? 0) === $expected) {
                    $warnings[] = "{$legacyTable}: {$skipped} legacy rows skipped during import (orphan FK / invalid data)";

                    continue;
                }

                if ($mappedRows < $expected) {
                    $warnings[] = "{$legacyTable}: {$skipped} legacy rows not mapped (likely skipped during import)";
                } elseif ($mappedRows > $expected) {
                    $errors[] = "{$legacyTable}: expected {$expected} legacy rows, found {$mappedRows} in legacy_import_maps";
                }
            }
        }

        $errors = array_merge($errors, $this->orphanChecks());

        foreach ($this->sampleProductImageWarnings() as $warning) {
            $warnings[] = $warning;
        }

        if ($imageUrlSampleSize > 0) {
            $errors = array_merge($errors, $this->verifyRemoteProductImageUrls($imageUrlSampleSize));
        }

        if ($reader !== null && $reader->hasTable('orders')) {
            $legacyTotal = 0.0;
            foreach ($reader->rows('orders') as $row) {
                $legacyTotal += (float) ($row['price_total'] ?? 0);
            }

            $importedTotal = (float) DB::table('orders')->sum('price_total');
            if (abs($legacyTotal - $importedTotal) > 0.01) {
                $errors[] = sprintf(
                    'orders: price_total mismatch (legacy %.2f vs imported %.2f)',
                    $legacyTotal,
                    $importedTotal,
                );
            }
        }

        if ($reader !== null && $reader->hasTable('users')) {
            $legacyBalance = 0.0;
            foreach ($reader->rows('users') as $row) {
                $legacyBalance += (float) ($row['balance'] ?? 0);
            }

            $importedBalance = (float) DB::table('users')->sum('wallet_balance');
            if (abs($legacyBalance - $importedBalance) > 0.01) {
                $errors[] = sprintf(
                    'users: wallet_balance mismatch (legacy %.2f vs imported %.2f)',
                    $legacyBalance,
                    $importedBalance,
                );
            }
        }

        if ($reader !== null && $reader->hasTable('earnings')) {
            $legacyEarnings = 0.0;
            foreach ($reader->rows('earnings') as $row) {
                $legacyEarnings += (float) ($row['earned_amount'] ?? 0);
            }

            $importedEarnings = (float) DB::table('vendor_earnings')->sum('earned_amount');
            if (abs($legacyEarnings - $importedEarnings) > 0.01) {
                $errors[] = sprintf(
                    'earnings: earned_amount mismatch (legacy %.2f vs imported %.2f)',
                    $legacyEarnings,
                    $importedEarnings,
                );
            }
        }

        if ($reader !== null && $reader->hasTable('transactions')) {
            $legacyPayments = 0.0;
            foreach ($reader->rows('transactions') as $row) {
                $legacyPayments += (float) ($row['payment_amount'] ?? $row['amount'] ?? 0);
            }

            $importedPayments = (float) DB::table('payment_transactions')->sum('amount');
            if (abs($legacyPayments - $importedPayments) > 0.01) {
                $errors[] = sprintf(
                    'transactions: payment amount mismatch (legacy %.2f vs imported %.2f)',
                    $legacyPayments,
                    $importedPayments,
                );
            }
        }

        if ($reader !== null && $reader->hasTable('escrow_transactions')) {
            $legacyEscrow = 0.0;
            foreach ($reader->rows('escrow_transactions') as $row) {
                // Importer stores item_price in escrow_transactions.amount
                $legacyEscrow += (float) ($row['item_price'] ?? 0);
            }

            $importedEscrow = (float) DB::table('escrow_transactions')->sum('amount');
            if (abs($legacyEscrow - $importedEscrow) > 0.01) {
                $errors[] = sprintf(
                    'escrow_transactions: amount mismatch (legacy %.2f vs imported %.2f)',
                    $legacyEscrow,
                    $importedEscrow,
                );
            }
        }

        if ($reader !== null && $reader->hasTable('wallet_expenses')) {
            $legacyExpenses = 0.0;
            foreach ($reader->rows('wallet_expenses') as $row) {
                $legacyExpenses += (float) ($row['expense_amount'] ?? $row['amount'] ?? 0);
            }

            $importedExpenses = (float) DB::table('wallet_transactions')->where('type', 'expense')->sum('amount');
            if (abs($legacyExpenses - $importedExpenses) > 0.01) {
                $errors[] = sprintf(
                    'wallet_expenses: amount mismatch (legacy %.2f vs imported %.2f)',
                    $legacyExpenses,
                    $importedExpenses,
                );
            }
        }

        return new LegacyImportVerificationResult($errors, $warnings);
    }

    private function actualTableCount(string $table): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    /**
     * @return list<string>
     */
    public function sampleProductImageWarnings(int $sampleSize = 5): array
    {
        $warnings = [];
        $images = DB::table('product_images')
            ->whereNotNull('path')
            ->orderBy('id')
            ->limit($sampleSize)
            ->get(['path']);

        foreach ($images as $image) {
            $path = (string) $image->path;
            if ($path === '') {
                continue;
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                continue;
            }

            if (str_starts_with($path, '/') || str_starts_with($path, 'uploads/') || preg_match('#^\d{6}/#', $path)) {
                continue;
            }

            $warnings[] = "product_images: suspicious relative path '{$path}'";
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    public function verifyRemoteProductImageUrls(int $sampleSize = 100): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        $errors = [];
        $images = DB::table('product_images')
            ->whereNotNull('path')
            ->orderBy('id')
            ->limit($sampleSize)
            ->get(['id', 'path', 'disk']);

        foreach ($images as $image) {
            $path = (string) $image->path;
            if ($path === '') {
                continue;
            }

            $url = $this->mediaUpload->urlFor($path, $image->disk ?? null);
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                continue;
            }

            try {
                $response = Http::timeout(15)->head($url);
                if (! $response->successful()) {
                    $response = Http::timeout(15)->get($url);
                }

                if (! $response->successful()) {
                    $errors[] = sprintf(
                        'product_images #%d: HTTP %d for %s',
                        $image->id,
                        $response->status(),
                        $url,
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = sprintf(
                    'product_images #%d: request failed for %s (%s)',
                    $image->id,
                    $url,
                    $e->getMessage(),
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function orphanChecks(): array
    {
        $errors = [];

        $checks = [
            ['order_items', 'orders', 'order_id'],
            ['comments', 'products', 'product_id'],
            ['messages', 'conversations', 'conversation_id'],
            ['support_messages', 'support_tickets', 'support_ticket_id'],
            ['followers.user_id', 'users', 'user_id'],
            ['followers.follower_id', 'users', 'follower_id'],
            ['product_option_values', 'product_options', 'product_option_id'],
            ['product_variants', 'products', 'product_id'],
            ['custom_field_product', 'products', 'product_id'],
            ['custom_field_product.custom_field_id', 'custom_fields', 'custom_field_id'],
            ['invoices', 'orders', 'order_id'],
            ['login_activities', 'users', 'user_id'],
            ['user_membership_plans', 'users', 'user_id'],
        ];

        foreach ($checks as [$label, $parentTable, $foreignKey]) {
            $childTable = str_contains($label, '.') ? explode('.', $label)[0] : $label;

            if (! $this->tableExists($childTable) || ! $this->tableExists($parentTable)) {
                continue;
            }

            $orphans = DB::table($childTable)
                ->leftJoin($parentTable, "{$parentTable}.id", '=', "{$childTable}.{$foreignKey}")
                ->whereNull("{$parentTable}.id")
                ->count();

            if ($orphans > 0) {
                $errors[] = "{$label}: {$orphans} orphan rows without matching parent records";
            }
        }

        return $errors;
    }

    private function expectedLegacyRowCount(?MySqlDumpReader $reader, string $legacyTable): ?int
    {
        if ($reader === null) {
            return null;
        }

        if ($reader->hasTable($legacyTable)) {
            return $reader->rowCount($legacyTable);
        }

        if ($legacyTable === 'referral_profiles' && $reader->hasTable('users')) {
            return $reader->rowCount('users');
        }

        return 0;
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
