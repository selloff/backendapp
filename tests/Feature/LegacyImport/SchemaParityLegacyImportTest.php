<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaParityLegacyImportTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('selloff:migrate', ['--fresh' => true]);
        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    }

    public function test_wallet_expense_links_order_by_order_number(): void
    {
        $this->seedTables('users', 'orders', 'order_items');

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--table' => 'wallet_expenses',
            '--skip-verify' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('wallet_transactions', [
            'legacy_id' => 1002,
            'type' => 'expense',
            'order_id' => 401,
            'payment_id' => 'EXP-1002',
            'currency_code' => 'NGN',
            'amount' => '26500.00',
        ]);
    }

    public function test_escrow_import_maps_product_stages_and_item_price(): void
    {
        $this->seedTables('users', 'products');

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--table' => 'escrow_transactions',
            '--skip-verify' => true,
        ])->assertSuccessful();

        $row = DB::table('escrow_transactions')->where('legacy_id', 4201)->first();
        $this->assertNotNull($row);
        $this->assertSame('ESC-4201', $row->ref);
        $this->assertSame(301, (int) $row->product_id);
        $this->assertEquals(25000.00, (float) $row->amount);
        $this->assertEquals(22500.00, (float) $row->seller_amount);
        $this->assertEquals(2500.00, (float) $row->commission_amount);
        $this->assertTrue((bool) $row->buyer_agreed);
        $this->assertTrue((bool) $row->seller_agreed);
        $this->assertTrue((bool) $row->payment_link_sent);
        $this->assertTrue((bool) $row->payment_received);
        $this->assertFalse((bool) $row->transaction_complete);
    }

    public function test_vendor_earnings_imports_line_item_breakdown(): void
    {
        $this->seedTables('users', 'orders', 'order_items');

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--table' => 'earnings',
            '--skip-verify' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('vendor_earnings', [
            'legacy_id' => 1101,
            'order_id' => 401,
            'order_item_id' => 501,
            'sale_amount' => '25000.00',
            'commission_amount' => '2500.00',
            'earned_amount' => '22500.00',
            'is_refunded' => false,
        ]);
    }

    public function test_invoice_archival_import_derives_totals_from_order(): void
    {
        $this->seedTables('users', 'orders', 'order_items');

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--table' => 'invoices',
            '--skip-verify' => true,
        ])->assertSuccessful();

        $invoice = DB::table('invoices')->where('legacy_id', 4101)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(401, (int) $invoice->order_id);
        $this->assertSame(900001, (int) $invoice->order_number);
        $this->assertEquals(26500.00, (float) $invoice->total_amount);
        $this->assertSame('NGN', $invoice->currency_code);
        $this->assertSame(103, (int) $invoice->buyer_id);

        $client = json_decode((string) $invoice->client_snapshot, true);
        $this->assertSame('legacybuyer', $client['client_username'] ?? null);
        $this->assertNotNull($invoice->line_items);
    }

    /**
     * @param  string  ...$tables
     */
    private function seedTables(string ...$tables): void
    {
        foreach ($tables as $table) {
            $this->artisan('selloff:import-legacy-data', [
                '--source' => $this->fixture,
                '--table' => $table,
                '--skip-verify' => true,
            ])->assertSuccessful();
        }
    }
}
