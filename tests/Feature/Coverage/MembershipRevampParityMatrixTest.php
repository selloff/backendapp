<?php

namespace Tests\Feature\Coverage;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MembershipRevampParityMatrixTest extends TestCase
{
    /**
     * @return list<array{legacy_path: string, spa_path: string}>
     */
    private function membershipRevampRows(): array
    {
        return [
            ['legacy_path' => 'admin/membership/edit_plan.php', 'spa_path' => '/admin/membership'],
            ['legacy_path' => 'admin/membership/membership_plans.php', 'spa_path' => '/admin/membership'],
            ['legacy_path' => 'dashboard/payments/membership_payments.php', 'spa_path' => '/vendor/membership'],
            ['legacy_path' => 'dashboard/product/products.php', 'spa_path' => '/vendor/products'],
            ['legacy_path' => 'product/select_membership_plan.php', 'spa_path' => '/vendor/membership/subscribe'],
            ['legacy_path' => 'product/select_membership.php', 'spa_path' => '/vendor/membership/subscribe'],
            ['legacy_path' => 'settings/social_media.php', 'spa_path' => '/settings'],
            ['legacy_path' => 'product/details/product.php', 'spa_path' => '/products/:slug'],
        ];
    }

    public function test_membership_revamp_matrix_rows_are_done_with_full_ui_parity(): void
    {
        $byPath = [];

        foreach ($this->matrixRows() as $row) {
            $byPath[$row['legacy_path']] = $row;
        }

        $violations = [];

        foreach ($this->membershipRevampRows() as $expected) {
            $row = $byPath[$expected['legacy_path']] ?? null;

            if ($row === null) {
                $violations[] = $expected['legacy_path'].' missing from spa-parity-matrix.csv';

                continue;
            }

            if ($row['status'] !== 'done' || $row['ui_parity'] !== 'full' || $row['spa_path'] !== $expected['spa_path']) {
                $violations[] = sprintf(
                    '%s expected done/full/%s got %s/%s/%s',
                    $expected['legacy_path'],
                    $expected['spa_path'],
                    $row['status'],
                    $row['ui_parity'],
                    $row['spa_path'],
                );
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_products_last_bumped_at_index_exists_after_migrate(): void
    {
        $this->artisan('selloff:migrate', ['--fresh' => true])->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('products', 'last_bumped_at'));
        $this->assertTrue($this->indexExists('products', 'products_last_bumped_at_index'));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $row = $connection->selectOne(
                'select 1 from pg_indexes where tablename = ? and indexname = ?',
                [$table, $indexName],
            );

            return $row !== null;
        }

        if ($driver === 'sqlite') {
            $rows = $connection->select("pragma index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $this->markTestSkipped('Index assertion not implemented for driver: '.$driver);

        return false;
    }

    /**
     * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
     */
    private function matrixRows(): array
    {
        $path = base_path('../docs/spa-parity-matrix.csv');
        $rows = [];
        $lines = array_slice(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))), 1);

        foreach ($lines as $line) {
            if (! preg_match('/^"([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)"$/', $line, $matches)) {
                continue;
            }

            $rows[] = [
                'legacy_path' => $matches[2],
                'spa_path' => $matches[3],
                'status' => $matches[4],
                'ui_parity' => $matches[5],
            ];
        }

        return $rows;
    }
}
